<?php
/**
 * Writer Engine - Personas Configuration
 * Manage AI writer personas and view performance metrics
 */

require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__, 3);

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

Env::load($root);
$config = new Config();
$pdo = ConnectionFactory::read($config);

$pageTitle = 'Writer Personas Configuration';

// Fetch persona stats
$sql = "
    SELECT wp.id, wp.name, wp.specialty, wp.hated_artist,
           COUNT(DISTINCT wa.id) as articles_generated,
           COUNT(DISTINCT CASE WHEN wa.status = 'published' THEN wa.id END) as articles_published,
           COUNT(DISTINCT CASE WHEN wa.safety_scan_status = 'flagged' THEN wa.id END) as articles_flagged,
           AVG(wa.total_engagement) as avg_engagement,
           wp.is_active
    FROM writer_personas wp
    LEFT JOIN writer_articles wa ON wp.id = wa.persona_id
    GROUP BY wp.id, wp.name, wp.specialty, wp.hated_artist, wp.is_active
    ORDER BY wp.id
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid p-4">
        <h1>🎭 Writer Personas</h1>
        <a href="/admin" class="btn btn-secondary mb-3">Back to Admin</a>

        <div class="row">
            <?php foreach ($personas as $persona): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($persona['name']); ?></h5>
                            <p class="card-text">
                                <strong><?php echo htmlspecialchars($persona['specialty']); ?></strong><br>
                                <span class="badge <?php echo $persona['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $persona['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </p>

                            <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">
                                <small>
                                    📝 Generated: <?php echo $persona['articles_generated']; ?><br>
                                    ✅ Published: <?php echo $persona['articles_published']; ?><br>
                                    ⚠️ Flagged: <?php echo $persona['articles_flagged']; ?><br>
                                    👍 Avg Engagement: <?php echo number_format($persona['avg_engagement'] ?? 0); ?>
                                </small>
                            </div>

                            <p class="text-muted" style="font-size: 0.85rem;">
                                <strong>Dislikes:</strong> <?php echo htmlspecialchars($persona['hated_artist']); ?>
                            </p>

                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#personaModal<?php echo $persona['id']; ?>">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
