<?php
/**
 * Writer Engine API Routes
 * Public and admin endpoints for article access and editorial workflow
 */

use NGN\Lib\Config;
use NGN\Lib\Http\Request;
use NGN\Lib\Http\JsonResponse;
use NGN\Lib\Writer\ArticleService;
use NGN\Lib\Writer\SafetyFilterService;

// Initialize services
$config = new Config();
$articleService = null;
$safetyService = null;

try {
    $articleService = new ArticleService($config);
    $safetyService = new SafetyFilterService($config);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Writer Engine services: " . $e->getMessage());
}

// ============================================================================
// PUBLIC ENDPOINTS (No Authentication)
// ============================================================================

// GET /api/v1/writer/articles - List published articles
$router->get('/writer/articles', function (Request $request) use ($articleService) {
    try {
        $filters = $request->query();
        $persona_id = (int)($filters['persona_id'] ?? 0);
        $limit = (int)($filters['limit'] ?? 20);
        $offset = (int)($filters['offset'] ?? 0);

        // Build query for published articles
        $pdo = ConnectionFactory::read(new Config());
        $sql = "
            SELECT wa.id, wa.title, wa.slug, wa.excerpt, wa.created_at,
                   wp.name as persona_name, wp.specialty,
                   wa.total_engagement, wa.views_count, wa.likes_count,
                   a.name as artist_name
            FROM writer_articles wa
            LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
            LEFT JOIN artists a ON wa.author_id = a.id
            WHERE wa.status = 'published'
        ";

        if ($persona_id > 0) {
            $sql .= " AND wa.persona_id = :persona_id";
        }

        $sql .= " ORDER BY wa.published_at DESC LIMIT :offset, :limit";

        $stmt = $pdo->prepare($sql);
        if ($persona_id > 0) {
            $stmt->bindParam(':persona_id', $persona_id, PDO::PARAM_INT);
        }
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse([
            'success' => true,
            'data' => $articles,
            'pagination' => ['offset' => $offset, 'limit' => $limit]
        ], 200);

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// GET /api/v1/writer/articles/{id} - Get single article
$router->get('/writer/articles/:id', function (Request $request) use ($articleService) {
    try {
        $articleId = (int)$request->param('id');

        $pdo = ConnectionFactory::read(new Config());
        $sql = "
            SELECT wa.id, wa.title, wa.slug, wa.excerpt, wa.content, wa.created_at,
                   wa.published_at, wp.name as persona_name, wp.specialty,
                   wa.total_engagement, wa.views_count, wa.likes_count, wa.comments_count,
                   a.name as artist_name
            FROM writer_articles wa
            LEFT JOIN writer_personas wp ON wa.persona_id = wp.id
            LEFT JOIN artists a ON wa.author_id = a.id
            WHERE wa.id = :id AND wa.status = 'published'
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $articleId]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            return new JsonResponse(['success' => false, 'message' => 'Article not found'], 404);
        }

        // Get persona comments if available
        $commentSql = "
            SELECT id, persona_id, comment_text, comment_type, upvote_count, created_at
            FROM writer_persona_comments
            WHERE article_id = :id AND is_published = 1
            ORDER BY created_at DESC
        ";

        $commentStmt = $pdo->prepare($commentSql);
        $commentStmt->execute([':id' => $articleId]);
        $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

        $article['comments'] = $comments;

        return new JsonResponse(['success' => true, 'data' => $article], 200);

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// ============================================================================
// ADMIN ENDPOINTS (Authentication Required)
// ============================================================================

// POST /api/v1/admin/writer/articles/{id}/claim - Claim article for editing
$router->post('/admin/writer/articles/:id/claim', function (Request $request) use ($articleService, $tokenSvc) {
    try {
        $currentUser = getCurrentUser($tokenSvc, $request);
        $authResult = checkEditorialAccess($currentUser);
        if (!$authResult['success']) {
            return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
        }

        $articleId = (int)$request->param('id');
        $editorId = (int)$currentUser['userId'];

        $success = $articleService->claimArticle($articleId, $editorId);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Article claimed'], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to claim article'], 400);
        }

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/writer/articles/{id}/approve - Approve and schedule publish
$router->post('/admin/writer/articles/:id/approve', function (Request $request) use ($articleService, $tokenSvc) {
    try {
        $currentUser = getCurrentUser($tokenSvc, $request);
        $authResult = checkEditorialAccess($currentUser);
        if (!$authResult['success']) {
            return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
        }

        $articleId = (int)$request->param('id');
        $editorId = (int)$currentUser['userId'];
        $body = json_decode($request->body(), true);
        $scheduledFor = $body['scheduled_for'] ?? null;

        $success = $articleService->approveArticle($articleId, $editorId, $scheduledFor);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Article approved'], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to approve article'], 400);
        }

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/writer/articles/{id}/reject - Reject article
$router->post('/admin/writer/articles/:id/reject', function (Request $request) use ($articleService, $tokenSvc) {
    try {
        $currentUser = getCurrentUser($tokenSvc, $request);
        $authResult = checkEditorialAccess($currentUser);
        if (!$authResult['success']) {
            return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
        }

        $articleId = (int)$request->param('id');
        $editorId = (int)$currentUser['userId'];
        $body = json_decode($request->body(), true);
        $reason = $body['reason'] ?? 'Rejected by editor';

        $success = $articleService->rejectArticle($articleId, $editorId, $reason);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Article rejected'], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to reject article'], 400);
        }

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/writer/articles/{id}/override-safety - Override safety flag
$router->post('/admin/writer/articles/:id/override-safety', function (Request $request) use ($safetyService, $tokenSvc) {
    try {
        $currentUser = getCurrentUser($tokenSvc, $request);
        $authResult = checkEditorialAccess($currentUser);
        if (!$authResult['success']) {
            return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
        }

        $articleId = (int)$request->param('id');
        $editorId = (int)$currentUser['userId'];
        $body = json_decode($request->body(), true);
        $reason = $body['reason'] ?? 'Admin override';

        $success = $safetyService->overrideSafetyFlag($articleId, $editorId, $reason);

        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Safety flag overridden'], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to override safety flag'], 400);
        }

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// GET /api/v1/admin/writer/metrics - Get performance metrics
$router->get('/admin/writer/metrics', function (Request $request) use ($tokenSvc) {
    try {
        $currentUser = getCurrentUser($tokenSvc, $request);
        $authResult = checkEditorialAccess($currentUser);
        if (!$authResult['success']) {
            return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
        }

        $pdo = ConnectionFactory::read(new Config());
        $days = (int)($request->query()['days'] ?? 7);

        $sql = "
            SELECT
                metric_date,
                SUM(articles_generated) as generated,
                SUM(articles_published) as published,
                SUM(articles_rejected) as rejected,
                SUM(total_cost_usd) as cost,
                AVG(safety_rejection_rate) as safety_rate
            FROM writer_generation_metrics
            WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY metric_date
            ORDER BY metric_date DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse([
            'success' => true,
            'data' => $metrics,
            'period_days' => $days
        ], 200);

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});
