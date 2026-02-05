<?php

// Suppress display errors in API to allow JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include necessary NGN bootstrap and configurations
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Env;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Http\{Request, Response, Router, JsonResponse};
use NGN\Lib\Auth\TokenService;
use NGN\Lib\Services\EventService;
use NGN\Lib\Services\TicketService;
use NGN\Lib\Services\TourService;
use NGN\Lib\Services\BookingService;
use NGN\Lib\Services\StripeConnectService;
use NGN\Lib\Legal\PlnPlaybackRepository;
use NGN\Lib\Legal\PlnPlaybackService;
use App\Lib\Editorial\EditorialService; // EditorialService namespace
use App\Lib\Commerce\ServiceOrderManager; // ServiceOrderManager namespace
use NGN\Lib\AI\MixFeedbackAssistant; // AI Mix Feedback
use NGN\Lib\Sparks\Exception\InsufficientFundsException;
use NGN\Lib\Sparks\SparkRepository;
use NGN\Lib\Sparks\SparkService;
use NGN\Lib\Commerce\ProductService;
use NGN\Lib\Commerce\PrintfulService;
use NGN\Lib\Commerce\OrderService;
use NGN\Lib\Commerce\InvestmentService;
use NGN\Lib\Posts\PostService;
use NGN\Lib\Feed\FeedService;
use NGN\Lib\Fans\LibraryService;
use NGN\Lib\Services\Advertiser\AdvertiserService;
use NGN\Lib\Stations\StationContentService;
use NGN\Lib\Stations\StationTierService;
use NGN\Lib\Stations\StationPlaylistService;
use NGN\Lib\Stations\ListenerRequestService;
use NGN\Lib\Stations\GeoBlockingService;
use NGN\Lib\Stations\StationStreamService;
use NGN\Lib\Services\Media\StreamingService;
use NGN\Lib\Services\Royalties\PlaybackService;
use NGN\Lib\Rankings\RankingService;
use NGN\Lib\Writer\{ArticleService as WriterArticleService, SafetyFilterService};
use NGN\Lib\Logging\LoggerFactory;

// Initialize configuration and database connections
$config = new Config();
$pdo = ConnectionFactory::write($config);
$logger = LoggerFactory::create($config, 'api');
$router = new Router();

// Instantiate Services (initialize each safely to prevent full API failure)
$editorialService = null;
$serviceOrderManager = null;
$productService = null;
$printfulService = null;
$orderService = null;
$engagementService = null;
$postService = null;
$feedService = null;
$royaltyService = null;
$investmentService = null;
$eventService = null;
$ticketService = null;
$tourService = null;
$bookingService = null;
$stripeConnectService = null;
$sparkRepository = null;
$sparkService = null;
$plnPlaybackRepository = null;
$plnPlaybackService = null;
$stationContentService = null;
$stationTierService = null;
$stationPlaylistService = null;
$listenerRequestService = null;
$geoBlockingService = null;
$stationStreamService = null;
$streamingService = null;
$playbackService = null;
$rankingService = null;
$metricsService = null;
$libraryService = null;
$advertiserService = null;
$timingMiddleware = null;

try {
    $editorialService = new EditorialService($pdo, $logger);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize EditorialService: " . $e->getMessage());
}

try {
    $serviceOrderManager = new ServiceOrderManager($pdo, $logger);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize ServiceOrderManager: " . $e->getMessage());
}

try {
    $productService = new ProductService($config);
    $printfulService = new PrintfulService($config);
    $orderService = new OrderService($config, $productService, $printfulService);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Commerce services: " . $e->getMessage());
}

try {
    $engagementService = new EngagementService($pdo);
    $postService = new PostService($config);
    $feedService = new FeedService($postService, $engagementService);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Feed/Post services: " . $e->getMessage());
}

try {
    $royaltyService = new RoyaltyLedgerService($pdo);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize RoyaltyLedgerService: " . $e->getMessage());
}

try {
    $investmentService = new InvestmentService($config);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize InvestmentService: " . $e->getMessage());
}

try {
    $eventService = new EventService($pdo);
    $ticketService = new TicketService($pdo);
    $tourService = new TourService($pdo);
    $bookingService = new BookingService($pdo, $eventService);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Event/Ticket/Tour/Booking services: " . $e->getMessage());
}

try {
    $stripeConnectService = new StripeConnectService($pdo, $config, $logger);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize StripeConnectService: " . $e->getMessage());
}

try {
    $sparkRepository = new SparkRepository($pdo);
    $sparkService = new SparkService($sparkRepository);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Spark services: " . $e->getMessage());
}

try {
    $plnPlaybackRepository = new PlnPlaybackRepository($pdo);
    $plnPlaybackService = new PlnPlaybackService($plnPlaybackRepository);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize PLN services: " . $e->getMessage());
}

try {
    $stationContentService = new StationContentService($config);
    $stationTierService = new StationTierService($config);
    $stationPlaylistService = new StationPlaylistService($config);
    $listenerRequestService = new ListenerRequestService($config);
    $geoBlockingService = new GeoBlockingService($config);
    $stationStreamService = new StationStreamService($pdo, $config);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Station services: " . $e->getMessage());
}

try {
    $streamingService = new StreamingService($config);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize StreamingService: " . $e->getMessage());
}

try {
    $playbackService = new PlaybackService($pdo);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize PlaybackService: " . $e->getMessage());
}

try {
    $rankingService = new RankingService($config);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize RankingService: " . $e->getMessage());
}

try {
    $libraryService = new LibraryService($pdo);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize LibraryService: " . $e->getMessage());
}

try {
    $advertiserService = new AdvertiserService($pdo);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize AdvertiserService: " . $e->getMessage());
}

try {
    $metricsService = new MetricsService($pdo);
    $timingMiddleware = new ApiTimingMiddleware($metricsService);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize Metrics services: " . $e->getMessage());
}

// Initialize TokenService for JWT authentication
$tokenSvc = null;
try {
    $tokenSvc = new TokenService($config);
} catch (\Throwable $e) {
    $logger->warning("Failed to initialize TokenService: " . $e->getMessage());
}

// --- Helper to get authenticated user ID and Role from JWT ---
function getCurrentUser($tokenSvc, $request): ?array
{
    $authHeader = $request->header('Authorization');
    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        return null; // Not authenticated
    }
    $token = trim(substr($authHeader, 7));
    try {
        $claims = $tokenSvc->decode($token);
        $userId = $claims['sub'] ?? null;
        $userRole = $claims['role'] ?? null; // Expecting role as a string like 'admin', 'editor'
        if ($userId && ctype_digit($userId)) {
            return ['userId' => (int)$userId, 'role' => strtolower($userRole ?? '')];
        }
    } catch (\Throwable $e) {
        error_log("API Auth Error: Token decoding failed - " . $e->getMessage());
        return null;
    }
    return null;
}

// --- Middleware for Role Check (Editorial Example) ---
function checkEditorialAccess(?array $user): array
{
    if (!$user) {
        return ['success' => false, 'message' => 'Authentication required.', 'statusCode' => 401];
    }
    $allowedRoles = ['admin', 'editor']; // Adjust 'editor' to the actual role name.
    if (!in_array($user['role'], $allowedRoles, true)) {
        return ['success' => false, 'message' => 'Forbidden: Insufficient privileges.', 'statusCode' => 403];
    }
    return ['success' => true, 'userId' => $user['userId'], 'role' => $user['role']];
}


// --- API Routes ---

// GET /api/v1/health - Basic system health check
$router->get('/health', function (Request $request) use ($config) {
    return new JsonResponse([
        'success' => true,
        'data' => [
            'status' => 'ok',
            'version' => Env::get('APP_VERSION', '0.1.0'),
            'time' => date('Y-m-d H:i:s'),
            'env' => Env::get('APP_ENV', 'production')
        ]
    ], 200);
});

require_once __DIR__ . '/admin_routes.php';
require_once __DIR__ . '/writer_routes.php';

// GET /api/v1/feed - Get the social feed
$router->get('/feed', function (Request $request) use ($feedService) {
    $filters = $request->query();
    $limit = (int)($filters['limit'] ?? 20);
    $offset = (int)($filters['offset'] ?? 0);
    unset($filters['limit'], $filters['offset']);

    try {
        $feed = $feedService->getFeed($filters, $limit, $offset);
        return new JsonResponse(['success' => true, 'data' => $feed], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// POST /orders
$router->post('/orders', function (Request $request) use ($orderService) {
    $body = $request->body();
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }

    $orderData = $data['order'] ?? [];
    $items = $data['items'] ?? [];

    $result = $orderService->create($orderData, $items);

    if ($result['success']) {
        return new JsonResponse($result, 201);
    } else {
        return new JsonResponse($result, 400);
    }
});


// POST /editorial/claim
$router->post('/editorial/claim', function (Request $request) use ($editorialService, $tokenSvc, $config) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    $authResult = checkEditorialAccess($currentUser);
    if (!$authResult['success']) {
        return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
    }
    $postId = $request->param('post_id');
    if (!$postId || !ctype_digit($postId)) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid post ID provided.'], 400);
    }
    if ($editorialService->claimPost((int)$postId, $authResult['userId'])) {
        return new JsonResponse(['success' => true, 'message' => 'Post claimed successfully.']);
    } else {
        return new JsonResponse(['success' => false, 'message' => 'Failed to claim post. It might be already claimed or invalid.']);
    }
});

// POST /editorial/publish
$router->post('/editorial/publish', function (Request $request) use ($editorialService, $tokenSvc, $config) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    $authResult = checkEditorialAccess($currentUser);
    if (!$authResult['success']) {
        return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
    }
    $postId = $request->param('post_id');
    if (!$postId || !ctype_digit($postId)) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid post ID provided.'], 400);
    }
    if ($editorialService->publishPost((int)$postId, $authResult['userId'])) {
        return new JsonResponse(['success' => true, 'message' => 'Post published successfully.']);
    } else {
        return new JsonResponse(['success' => false, 'message' => 'Failed to publish post. Ensure you are assigned to this post and it is ready for review.']);
    }
});

// POST /editorial/reject
$router->post('/editorial/reject', function (Request $request) use ($editorialService, $tokenSvc, $config) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    $authResult = checkEditorialAccess($currentUser);
    if (!$authResult['success']) {
        return new JsonResponse(['success' => false, 'message' => $authResult['message']], $authResult['statusCode']);
    }
    $postId = $request->param('post_id');
    $reason = $request->param('reason', '');
    if (!$postId || !ctype_digit($postId)) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid post ID provided.'], 400);
    }
    if ($editorialService->rejectPost((int)$postId, $authResult['userId'], $reason)) {
        return new JsonResponse(['success' => true, 'message' => 'Post rejected successfully.']);
    } else {
        return new JsonResponse(['success' => false, 'message' => 'Failed to reject post.']);
    }
});

// GET /api/v1/sparks/balance - Get user's Spark balance
$router->get('/sparks/balance', function (Request $request) use ($sparkService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $balance = $sparkService->getUserSparkBalance($currentUser['userId']);
        return new JsonResponse(['success' => true, 'data' => ['balance' => $balance]], 200);
    } catch (\Throwable $e) {
        error_log("Get Spark Balance Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching Spark balance.'], 500);
    }
});

// POST /api/v1/sparks/deduct - Deduct Sparks from user's balance
$router->post('/sparks/deduct', function (Request $request) use ($sparkService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $body = $request->body();
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }

    $amount = (int)($data['amount'] ?? 0);
    $reason = $data['reason'] ?? null;
    $metadata = $data['metadata'] ?? [];

    if ($amount <= 0) {
        return new JsonResponse(['success' => false, 'message' => 'Amount must be a positive integer.'], 400);
    }
    if (!$reason) {
        return new JsonResponse(['success' => false, 'message' => 'Reason for deduction is required.'], 400);
    }

    try {
        $sparkService->deductSparks($currentUser['userId'], $amount, $reason, $metadata);
        $newBalance = $sparkService->getUserSparkBalance($currentUser['userId']); // Get updated balance
        return new JsonResponse(['success' => true, 'message' => "Successfully deducted {$amount} Sparks.", 'data' => ['new_balance' => $newBalance]], 200);
    } catch (InsufficientFundsException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 402);
    } catch (\Throwable $e) {
        error_log("Deduct Spark Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error deducting Sparks.'], 500);
    }
});

// POST /ai/mix-feedback
$router->post('/ai/mix-feedback', function (Request $request) use ($tokenSvc, $config, $sparkService) {
    if (!$config->featureAiEnabled()) {
        return new JsonResponse(['success' => false, 'message' => 'AI services are currently disabled.'], 503);
    }
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $assistant = new MixFeedbackAssistant($config, $sparkService);
        // The Service expects an associative array. Since Request::json() might return an object or null, ensure it's an array.
        $body = $request->body(); // Get raw body
        $params = json_decode($body, true);
        if (!is_array($params)) {
             return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
        }

        $result = $assistant->analyze($user['userId'], $params);

        return new JsonResponse(['success' => true, 'data' => ['analysis' => $result]]);

    } catch (InvalidArgumentException $e) {
        return new JsonResponse(['success' => false, 'errors' => [['message' => $e->getMessage()]]], 400);
    } catch (InsufficientFundsException $e) {
        return new JsonResponse(['success' => false, 'errors' => [['message' => $e->getMessage()]]], 402);
    } catch (\Throwable $e) {
        error_log("AI Mix Feedback Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Internal server error processing feedback.'], 500);
    }
});

// --- New Route for Service Orders ---
$router->post('/services/order', function (Request $request) use ($serviceOrderManager, $tokenSvc, $config) {
    // Authenticate user for ordering services
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required to order services.'], 401);
    }

    // Get service details from request parameters
    $serviceType = $request->param('service_type');
    
    // Define prices for services (hardcoded as per instructions)
    $servicePrices = [
        'mix analysis' => 15.00, // For AI Mix Analysis (Sparks would need conversion logic)
        'professional mastering' => 50.00, // For Professional Mastering
        'radio promo campaign' => 250.00 // For Radio Promo Campaign
    ];

    // Normalize service type to lowercase for matching
    $normalizedServiceType = strtolower($serviceType ?? '');

    if (!array_key_exists($normalizedServiceType, $servicePrices)) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid or unsupported service type.'], 400);
    }

    $price = $servicePrices[$normalizedServiceType];

    // Call the ServiceOrderManager to create the order
    // Note: If 'AI Mix Analysis' should be paid in Sparks, this price needs to be handled differently.
    // For now, assuming all services are paid in fiat/Stripe.
    $result = $serviceOrderManager->createOrder($currentUser['userId'], $serviceType, $price);

    if ($result['success']) {
        // If Stripe integration is planned, check for sessionId and redirect or return it.
        // For now, returning a generic success message.
        return new JsonResponse(['success' => true, 'message' => $result['message'], 'orderId' => $result['orderId'] ?? null]);
    } else {
        return new JsonResponse(['success' => false, 'message' => $result['message']], 500); // Use 500 for internal errors
    }
});

// GET /api/v1/tracks/search - Search for tracks
$router->get('/tracks/search', function (Request $request) use ($config) {
    $queryParams = $request->query();
    $search = $queryParams['q'] ?? '';
    $stationId = (int)($queryParams['station_id'] ?? 0);
    $limit = (int)($queryParams['limit'] ?? 10);

    if (empty($search)) {
        return new JsonResponse(['success' => false, 'message' => 'Search query is required.'], 400);
    }

    try {
        $pdo = ConnectionFactory::write($config);
        $results = [];

        // Search in ngn_2025.tracks
        $stmt = $pdo->prepare("
            SELECT t.id, t.title, a.name as artist_name, 'track' as type
            FROM `ngn_2025`.`tracks` t
            JOIN `ngn_2025`.`artists` a ON t.artist_id = a.id
            WHERE t.title LIKE ? OR a.name LIKE ?
            LIMIT ?
        ");
        $stmt->execute(['%'.$search.'%', '%'.$search.'%', $limit]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($tracks) {
            $results = array_merge($results, $tracks);
        }

        // Search in station_content (BYOS)
        if ($stationId > 0) {
            $stmt = $pdo->prepare("
                SELECT id, title, artist_name, 'station_content' as type
                FROM `ngn_2025`.`station_content`
                WHERE station_id = ? AND status = 'approved' AND (title LIKE ? OR artist_name LIKE ?)
                LIMIT ?
            ");
            $stmt->execute([$stationId, '%'.$search.'%', '%'.$search.'%', $limit]);
            $stationTracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($stationTracks) {
                $results = array_merge($results, $stationTracks);
            }
        }

        // Remove duplicates and limit results
        $uniqueResults = [];
        $keys = [];
        foreach ($results as $result) {
            $key = strtolower($result['title'] . '-' . $result['artist_name']);
            if (!isset($keys[$key])) {
                $uniqueResults[] = $result;
                $keys[$key] = true;
            }
        }
        
        $finalResults = array_slice($uniqueResults, 0, $limit);

        return new JsonResponse(['success' => true, 'data' => ['items' => $finalResults]], 200);

    } catch (\Throwable $e) {
        error_log("API Error: /tracks/search endpoint failed - " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error searching for tracks.'], 500);
    }
});

// GET /api/v1/tracks/:id/token - Generate streaming token for track
$router->get('/tracks/:id/token', function (Request $request) use ($streamingService, $config, $logger) {
    try {
        $trackId = (int)$request->param('id');
        if (!$trackId) {
            return new JsonResponse(['success' => false, 'message' => 'Track ID is required.'], 400);
        }

        // Get user ID if authenticated (optional for token generation)
        $userId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            try {
                $token = trim(substr($authHeader, 7));
                $tokenSvc = new TokenService($config);
                $claims = $tokenSvc->decode($token);
                $userId = $claims['sub'] ?? null;
            } catch (\Throwable $e) {
                // Continue without auth - guest streaming allowed
            }
        }

        // Get client IP address
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($ipAddress, ',') !== false) {
            $ips = array_map('trim', explode(',', $ipAddress));
            $ipAddress = $ips[0];
        }

        $tokenData = $streamingService->generateStreamToken($trackId, $userId, $ipAddress);

        return new JsonResponse(['success' => true, 'data' => $tokenData], 200);

    } catch (\Throwable $e) {
        $logger->error("Token generation error: " . $e->getMessage());
        $message = $e->getMessage();
        $statusCode = 500;

        if (strpos($message, 'Track not found') !== false) {
            $statusCode = 404;
        } elseif (strpos($message, 'disputed') !== false || strpos($message, 'unavailable') !== false) {
            $statusCode = 403;
        } elseif (strpos($message, 'no audio file') !== false) {
            $statusCode = 400;
        }

        return new JsonResponse(['success' => false, 'message' => $message], $statusCode);
    }
});

// GET /api/v1/tracks/:id/stream - Stream audio with signed token
$router->get('/tracks/:id/stream', function (Request $request) use ($streamingService, $logger) {
    try {
        $trackId = (int)$request->param('id');
        $token = trim($request->query('token', ''));

        if (!$trackId || !$token) {
            return new JsonResponse(['success' => false, 'message' => 'Track ID and token are required.'], 400);
        }

        // Get client IP address
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($ipAddress, ',') !== false) {
            $ips = array_map('trim', explode(',', $ipAddress));
            $ipAddress = $ips[0];
        }

        // Parse Range header for seeking support
        $rangeStart = null;
        $rangeEnd = null;
        $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
        if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $rangeStart = (int)$matches[1];
            if ($matches[2] !== '') {
                $rangeEnd = (int)$matches[2];
            }
        }

        // Stream the track (this outputs binary audio data)
        $streamingService->streamTrack($trackId, $token, $ipAddress, $rangeStart, $rangeEnd);
        exit(); // Stop execution after streaming

    } catch (\Throwable $e) {
        $logger->error("Stream error: " . $e->getMessage());
        $message = $e->getMessage();
        $statusCode = 500;

        if (strpos($message, 'Token') !== false || strpos($message, 'expired') !== false) {
            $statusCode = 401;
        } elseif (strpos($message, 'IP mismatch') !== false) {
            $statusCode = 403;
        } elseif (strpos($message, 'not found') !== false || strpos($message, 'inaccessible') !== false) {
            $statusCode = 404;
        }

        return new JsonResponse(['success' => false, 'message' => $message], $statusCode);
    }
});

// GET /api/v1/artists - Search for artists
$router->get('/artists', function (Request $request) use ($pdo) {
    $search = $request->query('search', '');
    $limit = (int)$request->query('per_page', 8);
    $offset = (int)$request->query('offset', 0);

    try {
        $where = $search !== '' ? "WHERE name LIKE :search" : '';
        $sql = "SELECT id, name, slug FROM `ngn_2025`.`artists` {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $artists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return new JsonResponse(['success' => true, 'data' => ['items' => $artists]], 200);
    } catch (\Throwable $e) {
        error_log("API Error: /artists endpoint failed - " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching artists.'], 500);
    }
});

// GET /api/v1/labels - Get all labels
$router->get('/labels', function (Request $request) use ($pdo) {
    $search = $request->query('search', '');
    $limit = (int)$request->query('per_page', 10);
    $offset = (int)$request->query('offset', 0);
    $top = (int)$request->query('top', null);

    if ($top !== null) {
        $limit = $top;
        $offset = 0;
    }

    try {
        $where = $search !== '' ? "WHERE name LIKE :search" : '';
        $sql = "SELECT id, name, slug, image_url FROM `ngn_2025`.`labels` {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $labels = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Add image path prefix
        foreach ($labels as &$label) {
            if (!empty($label['image_url']) && !str_starts_with($label['image_url'], '/')) {
                $label['image_url'] = "/uploads/labels/{$label['image_url']}";
            }
        }

        return new JsonResponse(['success' => true, 'data' => ['items' => $labels]], 200);
    } catch (\Throwable $e) {
        error_log("API Error: /labels endpoint failed - " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching labels.'], 500);
    }
});

// GET /api/v1/stations - Get all stations
$router->get('/stations', function (Request $request) use ($pdo) {
    $search = $request->query('search', '');
    $limit = (int)$request->query('per_page', 10);
    $offset = (int)$request->query('offset', 0);
    $top = (int)$request->query('top', null);

    if ($top !== null) {
        $limit = $top;
        $offset = 0;
    }

    try {
        $where = $search !== '' ? "WHERE name LIKE :search" : '';
        $sql = "SELECT id, name, slug, image_url FROM `ngn_2025`.`stations` {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $stations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Add image path prefix
        foreach ($stations as &$station) {
            if (!empty($station['image_url']) && !str_starts_with($station['image_url'], '/')) {
                $station['image_url'] = "/uploads/stations/{$station['image_url']}";
            }
        }

        return new JsonResponse(['success' => true, 'data' => ['items' => $stations]], 200);
    } catch (\Throwable $e) {
        error_log("API Error: /stations endpoint failed - " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching stations.'], 500);
    }
});

// GET /api/v1/venues - Get all venues
$router->get('/venues', function (Request $request) use ($pdo) {
    $search = $request->query('search', '');
    $limit = (int)$request->query('per_page', 10);
    $offset = (int)$request->query('offset', 0);
    $top = (int)$request->query('top', null);

    if ($top !== null) {
        $limit = $top;
        $offset = 0;
    }

    try {
        $where = $search !== '' ? "WHERE name LIKE :search" : '';
        $sql = "SELECT id, name, slug, image_url FROM `ngn_2025`.`venues` {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        if ($search !== '') $stmt->bindValue(':search', '%'.$search.'%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Add image path prefix
        foreach ($venues as &$venue) {
            if (!empty($venue['image_url']) && !str_starts_with($venue['image_url'], '/')) {
                $venue['image_url'] = "/uploads/venues/{$venue['image_url']}";
            }
        }

        return new JsonResponse(['success' => true, 'data' => ['items' => $venues]], 200);
    } catch (\Throwable $e) {
        error_log("API Error: /venues endpoint failed - " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching venues.'], 500);
    }
});

// ============================================
// ADVERTISER API ENDPOINTS
// ============================================

// GET /api/v1/advertiser/campaigns - Get my campaign requests
$router->get('/advertiser/campaigns', function (Request $request) use ($advertiserService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user || $user['role_id'] != 11 && $user['role_id'] != 1) {
        return new JsonResponse(['success' => false, 'message' => 'Unauthorized or not an advertiser'], 403);
    }
    if (!$advertiserService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $requests = $advertiserService->getRequests($user['id']);
    return new JsonResponse(['success' => true, 'data' => ['items' => $requests]], 200);
});

// POST /api/v1/advertiser/campaigns - Submit a campaign request
$router->post('/advertiser/campaigns', function (Request $request) use ($advertiserService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user || $user['role_id'] != 11 && $user['role_id'] != 1) {
        return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    if (!$advertiserService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $data = $request->json();
    $id = $advertiserService->submitRequest($user['id'], $data);

    return new JsonResponse(['success' => true, 'message' => 'Campaign request submitted', 'id' => $id], 201);
});

// POST /api/v1/advertiser/campaigns/draft - AI Drafting Assistant
$router->post('/advertiser/campaigns/draft', function (Request $request) use ($advertiserService, $tokenSvc, $config) {
    if (!$config->featureAiEnabled()) {
        return new JsonResponse(['success' => false, 'message' => 'AI services are currently disabled.'], 503);
    }
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$advertiserService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $data = $request->json();
    $objective = $data['objective'] ?? '';
    $type = $data['campaign_type'] ?? 'display';

    if (empty($objective)) return new JsonResponse(['success' => false, 'message' => 'Objective required'], 400);

    $suggestions = $advertiserService->generateSuggestions($objective, $type);
    return new JsonResponse(['success' => true, 'data' => $suggestions], 200);
});

// GET /api/v1/admin/advertiser/campaigns - Admin review all requests
$router->get('/admin/advertiser/campaigns', function (Request $request) use ($advertiserService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user || $user['role_id'] != 1) return new JsonResponse(['success' => false, 'message' => 'Admin only'], 403);
    
    // We'll use a slightly different method or just raw query for all
    // For now, let's just use the service with a special case or get all
    // I'll add a getAllRequests method to AdvertiserService later if needed, 
    // but for now let's just query directly here for speed.
    $pdo = ConnectionFactory::read(new Config());
    $stmt = $pdo->query("SELECT cr.*, u.email as advertiser_email FROM `ngn_2025`.`campaign_requests` cr JOIN `ngn_2025`.`users` u ON cr.user_id = u.id ORDER BY cr.created_at DESC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return new JsonResponse(['success' => true, 'data' => ['items' => $items]], 200);
});

// PATCH /api/v1/admin/advertiser/campaigns/:id - Update status
$router->patch('/admin/advertiser/campaigns/(\d+)', function (Request $request, $id) use ($advertiserService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user || $user['role_id'] != 1) return new JsonResponse(['success' => false, 'message' => 'Admin only'], 403);
    if (!$advertiserService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $data = $request->json();
    $status = $data['status'] ?? 'pending';
    $notes = $data['admin_notes'] ?? null;

    $success = $advertiserService->updateStatus((int)$id, $status, $notes);
    return new JsonResponse(['success' => $success], $success ? 200 : 500);
});

// ============================================
// LIBRARY (ME) API ENDPOINTS
// ============================================

// GET /api/v1/me/favorites - Get user favorites
$router->get('/me/favorites', function (Request $request) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $type = $request->query('type', null);
    $limit = (int)$request->query('per_page', 50);
    $offset = (int)$request->query('offset', 0);

    try {
        $data = $libraryService->getFavorites($user['id'], $type, $limit, $offset);
        return new JsonResponse(['success' => true, 'data' => ['items' => $data]], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error fetching favorites'], 500);
    }
});

// POST /api/v1/me/favorites - Add to favorites
$router->post('/me/favorites', function (Request $request) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $data = $request->json();
    $type = $data['entity_type'] ?? '';
    $id = (int)($data['entity_id'] ?? 0);

    if (!$type || !$id) return new JsonResponse(['success' => false, 'message' => 'Missing entity info'], 400);

    try {
        $libraryService->addFavorite($user['id'], $type, $id);
        return new JsonResponse(['success' => true, 'message' => 'Added to favorites'], 201);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error adding favorite'], 500);
    }
});

// DELETE /api/v1/me/favorites/:type/:id - Remove from favorites
$router->delete('/me/favorites/([^/]+)/(\d+)', function (Request $request, $type, $id) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    try {
        $libraryService->removeFavorite($user['id'], $type, (int)$id);
        return new JsonResponse(['success' => true, 'message' => 'Removed from favorites'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error removing favorite'], 500);
    }
});

// GET /api/v1/me/follows - Get followed artists
$router->get('/me/follows', function (Request $request) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $limit = (int)$request->query('per_page', 50);
    $offset = (int)$request->query('offset', 0);

    try {
        $data = $libraryService->getFollowedArtists($user['id'], $limit, $offset);
        return new JsonResponse(['success' => true, 'data' => ['items' => $data]], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error fetching followed artists'], 500);
    }
});

// POST /api/v1/me/follows/:id - Follow an artist
$router->post('/me/follows/(\d+)', function (Request $request, $id) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    try {
        $libraryService->follow($user['id'], (int)$id);
        return new JsonResponse(['success' => true, 'message' => 'Followed artist'], 201);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error following artist'], 500);
    }
});

// DELETE /api/v1/me/follows/:id - Unfollow an artist
$router->delete('/me/follows/(\d+)', function (Request $request, $id) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    try {
        $libraryService->unfollow($user['id'], (int)$id);
        return new JsonResponse(['success' => true, 'message' => 'Unfollowed artist'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error unfollowing artist'], 500);
    }
});

// GET /api/v1/me/history - Get playback history
$router->get('/me/history', function (Request $request) use ($libraryService, $tokenSvc) {
    $user = getCurrentUser($tokenSvc, $request);
    if (!$user) return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    if (!$libraryService) return new JsonResponse(['success' => false, 'message' => 'Service unavailable'], 500);

    $limit = (int)$request->query('per_page', 50);
    $offset = (int)$request->query('offset', 0);

    try {
        $data = $libraryService->getHistory($user['id'], $limit, $offset);
        return new JsonResponse(['success' => true, 'data' => ['items' => $data]], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error fetching history'], 500);
    }
});

// ============================================
// RANKINGS API ENDPOINTS
// ============================================

// GET /api/v1/rankings/artists - Get artist rankings
$router->get('/rankings/artists', function (Request $request) use ($rankingService) {
    if (!$rankingService) {
        return new JsonResponse(['success' => false, 'message' => 'Ranking service unavailable.'], 500);
    }

    $interval = $request->query('interval', 'daily');
    $page = (int)$request->query('page', 1);
    $perPage = (int)$request->query('per_page', 10);
    $sort = $request->query('sort', 'rank');
    $dir = $request->query('dir', 'asc');

    try {
        $data = $rankingService->list('artists', $interval, $page, $perPage, $sort, $dir);
        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'interval' => $interval
        ], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error fetching artist rankings: ' . $e->getMessage()], 500);
    }
});

// GET /api/v1/rankings/labels - Get label rankings
$router->get('/rankings/labels', function (Request $request) use ($rankingService) {
    if (!$rankingService) {
        return new JsonResponse(['success' => false, 'message' => 'Ranking service unavailable.'], 500);
    }

    $interval = $request->query('interval', 'daily');
    $page = (int)$request->query('page', 1);
    $perPage = (int)$request->query('per_page', 10);
    $sort = $request->query('sort', 'rank');
    $dir = $request->query('dir', 'asc');

    try {
        $data = $rankingService->list('labels', $interval, $page, $perPage, $sort, $dir);
        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'interval' => $interval
        ], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error fetching label rankings: ' . $e->getMessage()], 500);
    }
});

// GET /api/v1/rankings/genres - Get genre rankings
$router->get('/rankings/genres', function (Request $request) use ($rankingService) {
    if (!$rankingService) {
        return new JsonResponse(['success' => false, 'message' => 'Ranking service unavailable.'], 500);
    }

    $interval = $request->query('interval', 'daily');
    $page = (int)$request->query('page', 1);
    $perPage = (int)$request->query('per_page', 10);
    $sort = $request->query('sort', 'rank');
    $dir = $request->query('dir', 'asc');

    try {
        $data = $rankingService->list('genres', $interval, $page, $perPage, $sort, $dir);
        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'interval' => $interval
        ], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Error fetching genre rankings: ' . $e->getMessage()], 500);
    }
});

// ============================================
// ENGAGEMENT API ENDPOINTS
// ============================================

// POST /api/v1/engagements - Create engagement (like, share, comment, spark)
$router->post('/engagements', function (Request $request) use ($engagementService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $entityType = $request->param('entity_type');
    $entityId = (int)$request->param('entity_id');
    $type = $request->param('type'); // like, share, comment, spark

    // Validate required fields
    if (!$entityType || !$entityId || !$type) {
        return new JsonResponse(['success' => false, 'message' => 'entity_type, entity_id, and type are required.'], 400);
    }

    // Metadata
    $metadata = [];
    if ($request->param('comment_text')) {
        $metadata['comment_text'] = $request->param('comment_text');
    }
    if ($request->param('spark_amount')) {
        $metadata['spark_amount'] = (int)$request->param('spark_amount');
    }
    if ($request->param('share_platform')) {
        $metadata['share_platform'] = $request->param('share_platform');
    }

    try {
        $engagement = $engagementService->create($userId, $entityType, $entityId, $type, $metadata);
        return new JsonResponse(['success' => true, 'data' => $engagement], 201);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/engagements/{entity_type}/{entity_id} - List engagements for entity
$router->get('/engagements/:entity_type/:entity_id', function (Request $request) use ($engagementService) {
    $entityType = $request->param('entity_type');
    $entityId = (int)$request->param('entity_id');

    $filters = [
        'type' => $request->query('type'), // Filter by engagement type
        'limit' => (int)($request->query('limit') ?? 50),
        'offset' => (int)($request->query('offset') ?? 0)
    ];

    try {
        $engagements = $engagementService->list($entityType, $entityId, $filters);
        $counts = $engagementService->getCounts($entityType, $entityId);

        return new JsonResponse([
            'success' => true,
            'data' => $engagements,
            'counts' => $counts
        ], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/engagements/counts/{entity_type}/{entity_id} - Get engagement counts only
$router->get('/engagements/counts/:entity_type/:entity_id', function (Request $request) use ($engagementService) {
    $entityType = $request->param('entity_type');
    $entityId = (int)$request->param('entity_id');

    try {
        $counts = $engagementService->getCounts($entityType, $entityId);
        return new JsonResponse(['success' => true, 'data' => $counts], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// DELETE /api/v1/engagements/{id} - Delete engagement (soft delete)
$router->delete('/engagements/:id', function (Request $request) use ($engagementService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $engagementId = (int)$request->param('id');

    try {
        $engagementService->delete($engagementId, $userId);
        return new JsonResponse(['success' => true, 'message' => 'Engagement deleted.'], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 403);
    }
});

// GET /api/v1/engagements/check/{entity_type}/{entity_id}/{type} - Check if user has engaged
$router->get('/engagements/check/:entity_type/:entity_id/:type', function (Request $request) use ($engagementService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $entityType = $request->param('entity_type');
    $entityId = (int)$request->param('entity_id');
    $type = $request->param('type');

    try {
        $hasEngaged = $engagementService->hasEngaged($userId, $entityType, $entityId, $type);
        return new JsonResponse(['success' => true, 'has_engaged' => $hasEngaged], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/engagements/notifications - Get user's engagement notifications
$router->get('/engagements/notifications', function (Request $request) use ($engagementService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];

    $filters = [
        'is_read' => $request->query('is_read') !== null ? (bool)$request->query('is_read') : null,
        'limit' => (int)($request->query('limit') ?? 50),
        'offset' => (int)($request->query('offset') ?? 0)
    ];

    try {
        $notifications = $engagementService->getNotifications($userId, $filters);
        return new JsonResponse(['success' => true, 'data' => $notifications], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// PUT /api/v1/engagements/notifications/{id}/read - Mark notification as read
$router->put('/engagements/notifications/:id/read', function (Request $request) use ($engagementService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $notificationId = (int)$request->param('id');

    try {
        $success = $engagementService->markNotificationRead($notificationId, $userId);
        if ($success) {
            return new JsonResponse(['success' => true, 'message' => 'Notification marked as read.'], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Notification not found.'], 404);
        }
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// ============================================
// ROYALTY & PAYOUTS (Bible Ch. 13 & 14)
// ============================================

// POST /api/v1/royalties/spark - Record a spark transaction (1 Spark = $0.01 USD)
$router->post('/royalties/spark', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $fromUserId = $currentUser['userId'];
    $entityType = $request->param('entity_type'); // artist, label, venue, station, post, video
    $entityId = (int)$request->param('entity_id');
    $sparkCount = (int)$request->param('spark_count'); // Number of sparks to send

    // Validate required fields
    if (!$entityType || !$entityId || $sparkCount < 1) {
        return new JsonResponse([
            'success' => false,
            'message' => 'entity_type, entity_id, and spark_count (>= 1) are required.'
        ], 400);
    }

    // Optional: engagement_id to link to a specific engagement
    $engagementId = $request->param('engagement_id') ? (int)$request->param('engagement_id') : null;

    try {
        // Bible: 1 Spark = $0.01 USD, 10% platform fee
        $transaction = $royaltyService->recordSpark(
            fromUserId: $fromUserId,
            entityType: $entityType,
            entityId: $entityId,
            sparkCount: $sparkCount,
            engagementId: $engagementId,
            paymentMethod: 'stripe', // Default to Stripe, can be overridden
            paymentReference: $request->param('payment_reference')
        );

        return new JsonResponse([
            'success' => true,
            'data' => $transaction,
            'message' => "Sent {$sparkCount} Sparks (\${$transaction['amount_gross']}) to {$entityType} #{$entityId}"
        ], 201);

    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/royalties/balance - Get user's wallet balance
$router->get('/royalties/balance', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];

    try {
        $balance = $royaltyService->getBalance($userId);
        return new JsonResponse(['success' => true, 'data' => $balance], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/royalties/transactions - Get user's transaction history
$router->get('/royalties/transactions', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $limit = (int)($request->query('limit') ?? 50);
    $offset = (int)($request->query('offset') ?? 0);

    try {
        $transactions = $royaltyService->getUserTransactions($userId, $limit, $offset);
        return new JsonResponse([
            'success' => true,
            'data' => $transactions,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($transactions)
            ]
        ], 200);
    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/royalties/payout - Request a payout
$router->post('/royalties/payout', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $amount = (float)$request->param('amount');
    $payoutMethod = $request->param('payout_method'); // stripe, paypal, crypto

    // Validate required fields
    if (!$amount || $amount <= 0) {
        return new JsonResponse(['success' => false, 'message' => 'Amount must be greater than 0.'], 400);
    }

    if (!in_array($payoutMethod, ['stripe', 'paypal', 'crypto'])) {
        return new JsonResponse([
            'success' => false,
            'message' => 'Invalid payout_method. Must be: stripe, paypal, or crypto.'
        ], 400);
    }

    try {
        $payoutRequest = $royaltyService->requestPayout($userId, $amount, $payoutMethod);
        return new JsonResponse([
            'success' => true,
            'data' => $payoutRequest,
            'message' => 'Payout request created. Pending admin approval.'
        ], 201);

    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/royalties/transaction/{id}/clear - Clear a pending transaction (Admin only)
$router->post('/royalties/transaction/:id/clear', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required - Admin only
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    if ($currentUser['role'] !== 'admin') {
        return new JsonResponse([
            'success' => false,
            'message' => 'Forbidden: Admin access required.'
        ], 403);
    }

    $txId = (int)$request->param('id');

    try {
        $transaction = $royaltyService->clearTransaction($txId);
        return new JsonResponse([
            'success' => true,
            'data' => $transaction,
            'message' => 'Transaction cleared successfully.'
        ], 200);

    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/royalties/transaction/{id}/refund - Refund a transaction (Admin only)
$router->post('/royalties/transaction/:id/refund', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required - Admin only
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    if ($currentUser['role'] !== 'admin') {
        return new JsonResponse([
            'success' => false,
            'message' => 'Forbidden: Admin access required.'
        ], 403);
    }

    $txId = (int)$request->param('id');
    $reason = $request->param('reason') ?? 'Admin refund';

    try {
        $transaction = $royaltyService->refundTransaction($txId, $reason);
        return new JsonResponse([
            'success' => true,
            'data' => $transaction,
            'message' => 'Transaction refunded successfully.'
        ], 200);

    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/royalties/eqs-distribution - Record EQS distribution (Admin only)
$router->post('/royalties/eqs-distribution', function (Request $request) use ($royaltyService, $tokenSvc) {
    // Auth required - Admin only
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    if ($currentUser['role'] !== 'admin') {
        return new JsonResponse([
            'success' => false,
            'message' => 'Forbidden: Admin access required.'
        ], 403);
    }

    $toUserId = (int)$request->param('user_id');
    $amount = (float)$request->param('amount');
    $metadata = $request->param('metadata') ? json_decode($request->param('metadata'), true) : [];

    // Validate required fields
    if (!$toUserId || !$amount || $amount <= 0) {
        return new JsonResponse([
            'success' => false,
            'message' => 'user_id and amount (> 0) are required.'
        ], 400);
    }

    try {
        // Bible Ch. 13.2.1: Monthly Creator Pool distribution (0% platform fee)
        $transaction = $royaltyService->recordEQSDistribution($toUserId, $amount, $metadata);
        return new JsonResponse([
            'success' => true,
            'data' => $transaction,
            'message' => "EQS distribution of \${$amount} credited to user #{$toUserId}"
        ], 201);

    } catch (Exception $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// ═══════════════════════════════════════════════════════════════
// INVESTMENT ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// POST /api/v1/investments/checkout - Create investment and checkout session
$router->post('/investments/checkout', function (Request $request) use ($investmentService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $amountCents = (int)$request->param('amount_cents');
    $email = trim((string)$request->param('email'));

    // Build URLs (use environment or request to get base URL)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'nextgennation.com';
    $baseUrl = "{$protocol}://{$host}";

    $successUrl = $request->param('success_url') ?: "{$baseUrl}/investments/success";
    $cancelUrl = $request->param('cancel_url') ?: "{$baseUrl}/investments/cancel";

    // Validate required fields
    if (!$amountCents || $amountCents < 50000) {
        return new JsonResponse([
            'success' => false,
            'message' => 'amount_cents is required and must be at least $500 (50000 cents).'
        ], 400);
    }

    if (!$email) {
        return new JsonResponse([
            'success' => false,
            'message' => 'email is required.'
        ], 400);
    }

    try {
        $result = $investmentService->createSession(
            $userId,
            $email,
            $amountCents,
            $successUrl,
            $cancelUrl
        );

        if (!$result['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to create checkout session.'
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'investment_id' => $result['investment_id'],
                'session_id' => $result['session_id'],
                'url' => $result['url'],
            ],
            'message' => 'Checkout session created successfully.'
        ], 201);

    } catch (Exception $e) {
        error_log("Investment checkout error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'message' => 'Internal server error creating checkout session.'
        ], 500);
    }
});

// GET /api/v1/investments/:id - Get investment details
$router->get('/investments/:id', function (Request $request) use ($investmentService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $investmentId = (int)$request->param('id');

    if (!$investmentId) {
        return new JsonResponse([
            'success' => false,
            'message' => 'Invalid investment ID.'
        ], 400);
    }

    try {
        $investment = $investmentService->getInvestment($investmentId);

        if (!$investment) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Investment not found.'
            ], 404);
        }

        // Only allow users to see their own investments (or admins)
        if ($investment['user_id'] !== $currentUser['userId'] && $currentUser['role'] !== 'admin') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Forbidden: You can only view your own investments.'
            ], 403);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $investment
        ], 200);

    } catch (Exception $e) {
        error_log("Get investment error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'message' => 'Internal server error.'
        ], 500);
    }
});

// GET /api/v1/investments - Get all investments for current user
$router->get('/investments', function (Request $request) use ($investmentService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];

    try {
        $investments = $investmentService->getUserInvestments($userId);

        return new JsonResponse([
            'success' => true,
            'data' => $investments,
            'count' => count($investments)
        ], 200);

    } catch (Exception $e) {
        error_log("Get user investments error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'message' => 'Internal server error.'
        ], 500);
    }
});


// ═══════════════════════════════════════════════════════════════
// SUBSCRIPTION & TIER API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

$router->post('/subscriptions/create-checkout-session', function(Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $body = json_decode($request->body(), true);
    $tierId = $body['tier_id'] ?? null;
    $billingPeriod = $body['billing_period'] ?? 'monthly'; // 'monthly' or 'annual'

    if (!$tierId) {
        return new JsonResponse(['success' => false, 'message' => 'Tier ID is required.'], 400);
    }

    if (!in_array($billingPeriod, ['monthly', 'annual'])) {
        return new JsonResponse(['success' => false, 'message' => 'Billing period must be monthly or annual.'], 400);
    }

    try {
        // Fetch tier details from the database
        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`subscription_tiers` WHERE id = ?");
        $stmt->execute([$tierId]);
        $tier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tier) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid tier.'], 404);
        }

        // Determine which price ID to use based on billing period
        $priceIdColumn = ($billingPeriod === 'annual') ? 'stripe_price_id_annual' : 'stripe_price_id_monthly';
        $priceId = $tier[$priceIdColumn] ?? null;

        if (!$priceId) {
            return new JsonResponse(['success' => false, 'message' => 'Tier does not have pricing configured. Please contact support.'], 400);
        }

        // Initialize Stripe
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        // Create checkout session with Stripe price ID
        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $_ENV['APP_URL'] . '/dashboard/station/tier.php?success=true',
            'cancel_url' => $_ENV['APP_URL'] . '/dashboard/station/tier.php?canceled=true',
            'client_reference_id' => $currentUser['userId'],
            'metadata' => [
                'tier_id' => $tierId,
                'user_id' => $currentUser['userId'],
            ]
        ]);

        return new JsonResponse(['success' => true, 'session_id' => $checkout_session->id, 'url' => $checkout_session->url]);

    } catch (\Throwable $e) {
        error_log("Stripe Checkout error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Could not create checkout session: ' . $e->getMessage()], 500);
    }
});

// ═══════════════════════════════════════════════════════════════
// QR CODE API ENDPOINTS
// ═══════════════════════════════════════════════════════════════
$router->post('/qr-codes', function(Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $body = json_decode($request->body(), true);
    $entityType = $body['entity_type'] ?? null;
    $entityId = $body['entity_id'] ?? null;

    if (!$entityType || !$entityId) {
        return new JsonResponse(['success' => false, 'message' => 'Entity type and ID are required.'], 400);
    }

    try {
        $targetUrl = $_ENV['BASEURL'] . '/' . $entityType . '/' . $entityId;
        $apiKey = $_ENV['MEQR_API_KEY'];

        $qrApiUrl = 'https://api.me-qr.com/v1/qr-code/generate';
        $postData = [
            'data' => $targetUrl,
            'size' => 300,
            'format' => 'png'
        ];

        $ch = curl_init($qrApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $qrData = json_decode($response, true);

        if ($httpCode === 200 && !empty($qrData['qr_code_url'])) {
            $qrCodeUrl = $qrData['qr_code_url'];

            $stmt = $pdo->prepare("
                INSERT INTO qr_codes (entity_type, entity_id, target_url, qr_image_url)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$entityType, $entityId, $targetUrl, $qrCodeUrl]);

            return new JsonResponse(['success' => true, 'qr_code_url' => $qrCodeUrl]);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to generate QR code.'], 500);
        }

    } catch (\Throwable $e) {
        error_log("QR Code generation error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Could not generate QR code.'], 500);
    }
});

// ═══════════════════════════════════════════════════════════════
// EVENT API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// POST /api/v1/pln/playback - Log PLN content playback
$router->post('/pln/playback', function (Request $request) use ($plnPlaybackService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    $userId = $currentUser['userId'] ?? null; // user_id is nullable

    $body = $request->body();
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }

    $trackId = (int)($data['track_id'] ?? 0);
    $stationId = (int)($data['station_id'] ?? null); // nullable
    $durationSeconds = (int)($data['duration_seconds'] ?? 0);
    $territory = $data['territory'] ?? 'XX'; // Default to 'XX' if not provided
    $listeners = (int)($data['listeners'] ?? 1);
    $playedAt = $data['played_at'] ?? null; // Optional, service will default to NOW()

    $ipAddress = $request->ip();
    $userAgent = $request->header('User-Agent');

    try {
        $logId = $plnPlaybackService->logPlayback(
            $trackId,
            $stationId,
            $userId,
            $durationSeconds,
            $territory,
            $listeners,
            $ipAddress,
            $userAgent,
            $playedAt
        );
        return new JsonResponse(['success' => true, 'message' => 'Playback logged successfully.', 'data' => ['log_id' => $logId]], 201);
    } catch (\InvalidArgumentException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        $logger->error("PLN Playback Log Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error logging PLN playback.'], 500);
    }
});

// POST /api/v1/playback/events - Record qualified listen for royalty tracking
$router->post('/playback/events', function (Request $request) use ($playbackService, $logger) {
    // Parse request body
    $data = json_decode($request->body(), true);

    if (!$data) {
        return new JsonResponse([
            'success' => false,
            'error' => 'Invalid JSON payload'
        ], 400);
    }

    // Validate required fields
    $trackId = (int)($data['track_id'] ?? 0);
    $sessionId = $data['session_id'] ?? null;
    $durationSeconds = (int)($data['duration_seconds'] ?? 0);
    $sourceType = $data['source_type'] ?? 'on_demand';
    $timestamp = $data['timestamp'] ?? null;

    if (!$trackId || !$sessionId || $durationSeconds < 30) {
        return new JsonResponse([
            'success' => false,
            'error' => 'Missing required fields or duration < 30 seconds'
        ], 400);
    }

    // Get user info (optional - may be null for anonymous)
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $request->ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Check for duplicate within 5-minute window
    $isDuplicate = $playbackService->isDuplicateEvent(
        $trackId,
        $sessionId,
        $userId,
        300 // 5 minutes in seconds
    );

    if ($isDuplicate) {
        error_log("[Playback API] Duplicate event detected: track={$trackId}, session={$sessionId}");
        return new JsonResponse([
            'success' => true,
            'message' => 'Event already recorded',
            'event_id' => null
        ], 200);
    }

    try {
        // Record playback event
        $eventId = $playbackService->recordQualifiedListen([
            'track_id' => $trackId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'duration_seconds' => $durationSeconds,
            'source_type' => $sourceType,
            'timestamp' => $timestamp,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $data['metadata'] ?? null
        ]);

        error_log("[Playback API] Qualified listen recorded: event_id={$eventId}, track={$trackId}");

        return new JsonResponse([
            'success' => true,
            'message' => 'Playback event recorded',
            'event_id' => $eventId
        ], 201);

    } catch (Exception $e) {
        error_log("[Playback API] Error recording event: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'error' => 'Failed to record playback event'
        ], 500);
    }
});

// ═══════════════════════════════════════════════════════════════
// STATION STREAMING API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// GET /api/v1/stations/:id/stream - Generate streaming token
$router->get('/stations/:id/stream', function (Request $request) use ($stationStreamService, $logger) {
    $stationId = (int)$request->param('id');
    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $request->ip();

    try {
        $tokenData = $stationStreamService->generateStreamToken($stationId, $userId, $ipAddress);

        return new JsonResponse([
            'success' => true,
            'data' => $tokenData
        ]);
    } catch (\Exception $e) {
        $logger->error("Station stream token error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 404);
    }
});

// GET /api/v1/stations/:id/now-playing - Get current track metadata
$router->get('/stations/:id/now-playing', function (Request $request) use ($stationStreamService) {
    $stationId = (int)$request->param('id');

    $metadata = $stationStreamService->getNowPlaying($stationId);

    return new JsonResponse([
        'success' => true,
        'data' => $metadata
    ]);
});

// GET /api/v1/stations/:id/info - Get station info with listener count
$router->get('/stations/:id/info', function (Request $request) use ($stationStreamService, $logger) {
    $stationId = (int)$request->param('id');

    try {
        $info = $stationStreamService->getStationInfo($stationId);

        return new JsonResponse([
            'success' => true,
            'data' => $info
        ]);
    } catch (\Exception $e) {
        $logger->error("Station info error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'error' => $e->getMessage()
        ], 404);
    }
});

// POST /api/v1/stations/:id/session/start - Start listening session
$router->post('/stations/:id/session/start', function (Request $request) use ($stationStreamService, $logger) {
    $stationId = (int)$request->param('id');
    $data = json_decode($request->body(), true);

    $sessionId = $data['session_id'] ?? null;
    if (!$sessionId) {
        return new JsonResponse([
            'success' => false,
            'error' => 'session_id required'
        ], 400);
    }

    $userId = $_SESSION['user_id'] ?? null;
    $ipAddress = $request->ip();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Detect territory (placeholder - use GeoBlockingService in production)
    $territory = 'XX';

    try {
        $sessionDbId = $stationStreamService->startSession(
            $stationId,
            $sessionId,
            $userId,
            $ipAddress,
            $userAgent,
            $territory
        );

        return new JsonResponse([
            'success' => true,
            'data' => ['session_id' => $sessionDbId]
        ]);
    } catch (\Exception $e) {
        $logger->error("Station session start error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'error' => 'Failed to start session'
        ], 500);
    }
});

// POST /api/v1/stations/:id/session/heartbeat - Send heartbeat
$router->post('/stations/:id/session/heartbeat', function (Request $request) use ($stationStreamService) {
    $stationId = (int)$request->param('id');
    $data = json_decode($request->body(), true);

    $sessionId = $data['session_id'] ?? null;
    if (!$sessionId) {
        return new JsonResponse([
            'success' => false,
            'error' => 'session_id required'
        ], 400);
    }

    $stationStreamService->heartbeat($stationId, $sessionId);

    return new JsonResponse(['success' => true]);
});

// POST /api/v1/stations/:id/session/end - End session
$router->post('/stations/:id/session/end', function (Request $request) use ($stationStreamService) {
    $stationId = (int)$request->param('id');
    $data = json_decode($request->body(), true);

    $sessionId = $data['session_id'] ?? null;
    if (!$sessionId) {
        return new JsonResponse([
            'success' => false,
            'error' => 'session_id required'
        ], 400);
    }

    $stationStreamService->endSession($stationId, $sessionId);

    return new JsonResponse(['success' => true]);
});

// POST /api/v1/events - Create a new event
$router->post('/events', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') { // Assuming only admins can create events
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }
    // Add indemnity_accepted to the data array for createEvent
    $data['indemnity_accepted'] = $data['indemnity_accepted'] ?? 0;
    try {
        $event = $eventService->createEvent($data);
        return new JsonResponse(['success' => true, 'data' => $event], 201);
    } catch (\Throwable $e) {
        $logger->error('Create Event Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error creating event.'], 500);
    }
});

// GET /api/v1/events/:id - Get a single event
$router->get('/events/:id', function (Request $request) use ($eventService, $logger) {
    $eventId = $request->param('id');
    try {
        $event = $eventService->getEvent($eventId);
        if (!$event) {
            return new JsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
        }
        return new JsonResponse(['success' => true, 'data' => $event], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Event Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error fetching event.'], 500);
    }
});

// GET /api/v1/events - List events
$router->get('/events', function (Request $request) use ($eventService, $logger) {
    $filters = $request->query(); // Get all query parameters as filters
    $limit = (int)($filters['limit'] ?? 50);
    $offset = (int)($filters['offset'] ?? 0);
    unset($filters['limit'], $filters['offset']); // Remove pagination from filters
    try {
        $events = $eventService->listEvents($filters, $limit, $offset);
        return new JsonResponse(['success' => true, 'data' => $events], 200);
    } catch (\Throwable $e) {
        $logger->error('List Events Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error listing events.'], 500);
    }
});

// PUT /api/v1/events/:id - Update an event
$router->put('/events/:id', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }
    // Add indemnity_accepted to the data array for updateEvent
    if (isset($data['indemnity_accepted'])) {
        $data['indemnity_accepted'] = (int)$data['indemnity_accepted'];
    }
    try {
        $event = $eventService->updateEvent($eventId, $data);
        return new JsonResponse(['success' => true, 'data' => $event], 200);
    } catch (\Throwable $e) {
        $logger->error('Update Event Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error updating event.'], 500);
    }
});

// DELETE /api/v1/events/:id - Delete an event
$router->delete('/events/:id', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    try {
        if ($eventService->deleteEvent($eventId)) {
            return new JsonResponse(['success' => true, 'message' => 'Event deleted successfully.'], 200);
        }
        return new JsonResponse(['success' => false, 'message' => 'Event not found or could not be deleted.'], 404);
    } catch (\Throwable $e) {
        $logger->error('Delete Event Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400); // Catch specific exceptions for better messages
    }
});

// PUT /api/v1/events/:id/publish - Publish an event
$router->put('/events/:id/publish', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    try {
        $event = $eventService->publishEvent($eventId);
        return new JsonResponse(['success' => true, 'data' => $event, 'message' => 'Event published successfully.'], 200);
    } catch (\Throwable $e) {
        $logger->error('Publish Event Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error publishing event.'], 500);
    }
});

// PUT /api/v1/events/:id/cancel - Cancel an event
$router->put('/events/:id/cancel', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    $data = json_decode($request->body(), true);
    $reason = $data['reason'] ?? null;
    try {
        $event = $eventService->cancelEvent($eventId, $reason);
        return new JsonResponse(['success' => true, 'data' => $event, 'message' => 'Event cancelled successfully.'], 200);
    } catch (\Throwable $e) {
        $logger->error('Cancel Event Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error cancelling event.'], 500);
    }
});

// POST /api/v1/events/:id/lineup - Add artist to lineup
$router->post('/events/:id/lineup', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }
    try {
        $lineup = $eventService->addToLineup($eventId, $data);
        return new JsonResponse(['success' => true, 'data' => $lineup], 201);
    } catch (\Throwable $e) {
        $logger->error('Add Lineup Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error adding to lineup.'], 500);
    }
});

// PUT /api/v1/events/:id/lineup/:lineupId - Update lineup entry
$router->put('/events/:id/lineup/:lineupId', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    $lineupId = (int)$request->param('lineupId');
    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }
    try {
        $lineup = $eventService->updateLineup($eventId, $lineupId, $data);
        return new JsonResponse(['success' => true, 'data' => $lineup], 200);
    } catch (\Throwable $e) {
        $logger->error('Update Lineup Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error updating lineup.'], 500);
    }
});

// DELETE /api/v1/events/:id/lineup/:lineupId - Remove artist from lineup
$router->delete('/events/:id/lineup/:lineupId', function (Request $request) use ($eventService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('id');
    $lineupId = (int)$request->param('lineupId');
    try {
        if ($eventService->removeFromLineup($eventId, $lineupId)) {
            return new JsonResponse(['success' => true, 'message' => 'Artist removed from lineup.'], 200);
        }
        return new JsonResponse(['success' => false, 'message' => 'Lineup entry not found or could not be removed.'], 404);
    } catch (\Throwable $e) {
        $logger->error('Remove Lineup Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error removing from lineup.'], 500);
    }
});

// GET /api/v1/events/:id/lineup - Get lineup for an event
$router->get('/events/:id/lineup', function (Request $request) use ($eventService, $logger) {
    $eventId = $request->param('id');
    try {
        $lineup = $eventService->getLineup($eventId);
        return new JsonResponse(['success' => true, 'data' => $lineup], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Lineup Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error fetching lineup.'], 500);
    }
});

// ═══════════════════════════════════════════════════════════════
// TICKET API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// POST /api/v1/tickets - Purchase a new ticket
$router->post('/tickets', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }
    $data['user_id'] = $currentUser['userId'];
    try {
        $ticket = $ticketService->createTicket($data);
        return new JsonResponse(['success' => true, 'data' => $ticket, 'message' => 'Ticket purchased successfully.'], 201);
    } catch (\Throwable $e) {
        $logger->error('Purchase Ticket Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/tickets/bulk - Purchase multiple tickets
$router->post('/tickets/bulk', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data['tickets'])) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body. Expected an array of tickets.'], 400);
    }
    $ticketsData = $data['tickets'];
    foreach ($ticketsData as &$ticketData) {
        $ticketData['user_id'] = $currentUser['userId']; // Assign current user to all tickets
    }
    try {
        $tickets = $ticketService->createTickets($ticketsData);
        return new JsonResponse(['success' => true, 'data' => $tickets, 'message' => 'Tickets purchased successfully.'], 201);
    } catch (\Throwable $e) {
        $logger->error('Bulk Purchase Ticket Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/tickets/redeem - Redeem a ticket by QR hash
$router->post('/tickets/redeem', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    // Bouncer mode might not require full auth, but for API, we assume a scanner is authenticated
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    // Potentially add a role check for 'bouncer' or 'admin' here
    // if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'bouncer') {
    //     return new JsonResponse(['success' => false, 'message' => 'Forbidden: Bouncer or Admin access required.'], 403);
    // }

    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['qr_hash'])) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body. qr_hash is required.'], 400);
    }
    $qrHash = $data['qr_hash'];
    $scanData = [
        'scanned_by' => $currentUser['userId'],
        'scan_location' => $data['scan_location'] ?? 'unknown',
        'device_id' => $data['device_id'] ?? null,
        'device_ip' => $request->ip(),
        'user_agent' => $request->header('User-Agent'),
        'latitude' => $data['latitude'] ?? null,
        'longitude' => $data['longitude'] ?? null,
    ];
    try {
        $result = $ticketService->redeemTicket($qrHash, $scanData);
        if ($result['success']) {
            return new JsonResponse($result, 200);
        }
        return new JsonResponse($result, 400); // Bad request for invalid ticket, etc.
    } catch (\Throwable $e) {
        $logger->error('Redeem Ticket Error: ' . $e->getMessage(), ['qr_hash' => $qrHash, 'trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error redeeming ticket.'], 500);
    }
});

// POST /api/v1/tickets/manifest/:event_id - Generate offline manifest for Bouncer Mode
$router->post('/tickets/manifest/:event_id', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') { // Only admins can generate manifests
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('event_id');
    try {
        $manifest = $ticketService->generateManifest($eventId);
        return new JsonResponse(['success' => true, 'data' => $manifest, 'message' => 'Offline manifest generated.'], 201);
    } catch (\Throwable $e) {
        $logger->error('Generate Manifest Error: ' . $e->getMessage(), ['event_id' => $eventId, 'trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error generating manifest.'], 500);
    }
});

// POST /api/v1/tickets/sync-offline - Sync offline redemptions from Bouncer Mode
$router->post('/tickets/sync-offline', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) { // Bouncer app would authenticate
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    // if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'bouncer') {
    //     return new JsonResponse(['success' => false, 'message' => 'Forbidden: Bouncer or Admin access required.'], 403);
    // }

    $data = json_decode($request->body(), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data['redemptions'])) {
        return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body. Expected an array of redemptions.'], 400);
    }
    $redemptions = $data['redemptions'];
    try {
        $results = $ticketService->syncOfflineRedemptions($redemptions);
        return new JsonResponse(['success' => true, 'data' => $results, 'message' => 'Offline redemptions synced.'], 200);
    } catch (\Throwable $e) {
        $logger->error('Sync Offline Redemptions Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error syncing offline redemptions.'], 500);
    }
});

// GET /api/v1/tickets/:id - Get a single ticket
$router->get('/tickets/:id', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    $ticketId = $request->param('id');
    try {
        $ticket = $ticketService->getTicket($ticketId);
        if (!$ticket) {
            return new JsonResponse(['success' => false, 'message' => 'Ticket not found.'], 404);
        }
        // Ensure user can only view their own tickets or if they are admin
        if ((string)$ticket['user_id'] !== (string)$currentUser['userId'] && $currentUser['role'] !== 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Forbidden: You can only view your own tickets.'], 403);
        }
        return new JsonResponse(['success' => true, 'data' => $ticket], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Ticket Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error fetching ticket.'], 500);
    }
});

// GET /api/v1/tickets/user/:user_id - Get tickets for a user
$router->get('/tickets/user/:user_id', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    $userId = (int)$request->param('user_id');
    // Enforce that a user can only request their own tickets, unless admin
    if ((string)$userId !== (string)$currentUser['userId'] && $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: You can only view your own tickets.'], 403);
    }
    $filters = $request->query();
    try {
        $tickets = $ticketService->getUserTickets($userId, $filters);
        return new JsonResponse(['success' => true, 'data' => $tickets], 200);
    } catch (\Throwable $e) {
        $logger->error('Get User Tickets Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error fetching user tickets.'], 500);
    }
});

// GET /api/v1/tickets/event/:event_id - Get tickets for an event
$router->get('/tickets/event/:event_id', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') { // Only admins can view all tickets for an event
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('event_id');
    $filters = $request->query();
    try {
        $tickets = $ticketService->getEventTickets($eventId, $filters);
        return new JsonResponse(['success' => true, 'data' => $tickets], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Event Tickets Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error fetching event tickets.'], 500);
    }
});

// GET /api/v1/events/:event_id/tickets/stats - Get ticket statistics for an event
$router->get('/events/:event_id/tickets/stats', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') { // Only admins can view ticket stats
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $eventId = $request->param('event_id');
    try {
        $stats = $ticketService->getEventStats($eventId);
        return new JsonResponse(['success' => true, 'data' => $stats], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Event Ticket Stats Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => 'Error fetching event ticket statistics.'], 500);
    }
});

// PUT /api/v1/tickets/:id/refund - Refund a ticket
$router->put('/tickets/:id/refund', function (Request $request) use ($ticketService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') { // Only admins can refund tickets
        return new JsonResponse(['success' => false, 'message' => 'Forbidden: Admin access required.'], 403);
    }
    $ticketId = $request->param('id');
    $data = json_decode($request->body(), true);
    $reason = $data['reason'] ?? null;
    try {
        if ($ticketService->refundTicket($ticketId, $reason)) {
            return new JsonResponse(['success' => true, 'message' => 'Ticket refunded successfully.'], 200);
        }
        return new JsonResponse(['success' => false, 'message' => 'Ticket not found or could not be refunded.'], 404);
    } catch (\Throwable $e) {
        $logger->error('Refund Ticket Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// ═══════════════════════════════════════════════════════════════
// TOUR API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// GET /api/v1/tours - List tours with filters (public)
$router->get('/tours', function (Request $request) use ($tourService, $logger) {
    try {
        $filters = [];
        $artistId = $request->query('artist_id');
        $status = $request->query('status');
        $upcoming = $request->query('upcoming');
        $limit = (int)($request->query('limit') ?? 50);
        $offset = (int)($request->query('offset') ?? 0);

        if ($artistId) $filters['artist_id'] = (int)$artistId;
        if ($status) $filters['status'] = $status;
        if ($upcoming) $filters['upcoming'] = true;

        $tours = $tourService->listTours($filters, $limit, $offset);

        return new JsonResponse([
            'success' => true,
            'data' => $tours,
            'count' => count($tours)
        ], 200);
    } catch (\Throwable $e) {
        $logger->error('List Tours Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error listing tours.'], 400);
    }
});

// GET /api/v1/tours/:id - Get tour details (public)
$router->get('/tours/:id', function (Request $request) use ($tourService, $logger) {
    try {
        $tourId = $request->param('id');
        $tour = $tourService->getTour($tourId);

        if (!$tour) {
            return new JsonResponse(['success' => false, 'message' => 'Tour not found.'], 404);
        }

        return new JsonResponse(['success' => true, 'data' => $tour], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Tour Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching tour.'], 400);
    }
});

// POST /api/v1/tours - Create tour (requires auth)
$router->post('/tours', function (Request $request) use ($tourService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $data = json_decode($request->body(), true);
        $data['artist_id'] = $currentUser['userId'];

        $tour = $tourService->createTour($data);

        return new JsonResponse(['success' => true, 'data' => $tour], 201);
    } catch (\Throwable $e) {
        $logger->error('Create Tour Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// PUT /api/v1/tours/:id - Update tour (requires auth + ownership)
$router->put('/tours/:id', function (Request $request) use ($tourService, $tokenSvc, $logger, $pdo) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $tourId = $request->param('id');
        $tour = $tourService->getTour($tourId);

        if (!$tour || $tour['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized or tour not found.'], 403);
        }

        $data = json_decode($request->body(), true);
        $updated = $tourService->updateTour($tourId, $data);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Update Tour Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// DELETE /api/v1/tours/:id - Delete tour (requires auth + ownership)
$router->delete('/tours/:id', function (Request $request) use ($tourService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $tourId = $request->param('id');
        $tour = $tourService->getTour($tourId);

        if (!$tour || $tour['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized or tour not found.'], 403);
        }

        $tourService->deleteTour($tourId);

        return new JsonResponse(['success' => true, 'message' => 'Tour deleted.'], 200);
    } catch (\Throwable $e) {
        $logger->error('Delete Tour Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/tours/:id/dates - Add event to tour
$router->post('/tours/:id/dates', function (Request $request) use ($tourService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $tourId = $request->param('id');
        $tour = $tourService->getTour($tourId);

        if (!$tour || $tour['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized or tour not found.'], 403);
        }

        $data = json_decode($request->body(), true);
        $eventId = $data['event_id'] ?? null;
        $position = (int)($data['position'] ?? 1);

        if (!$eventId) {
            return new JsonResponse(['success' => false, 'message' => 'event_id is required.'], 400);
        }

        $tourService->addTourDate($tourId, $eventId, $position);
        $updated = $tourService->getTour($tourId);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Add Tour Date Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// DELETE /api/v1/tours/:tourId/dates/:eventId - Remove event from tour
$router->delete('/tours/:tourId/dates/:eventId', function (Request $request) use ($tourService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $tourId = $request->param('tourId');
        $eventId = $request->param('eventId');
        $tour = $tourService->getTour($tourId);

        if (!$tour || $tour['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized or tour not found.'], 403);
        }

        $tourService->removeTourDate($tourId, $eventId);
        $updated = $tourService->getTour($tourId);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Remove Tour Date Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// PUT /api/v1/tours/:id/announce - Announce tour
$router->put('/tours/:id/announce', function (Request $request) use ($tourService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $tourId = $request->param('id');
        $tour = $tourService->getTour($tourId);

        if (!$tour || $tour['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized or tour not found.'], 403);
        }

        $updated = $tourService->announceTour($tourId);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Announce Tour Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// ═══════════════════════════════════════════════════════════════
// BOOKING REQUEST API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// POST /api/v1/bookings - Create booking request (requires auth)
$router->post('/bookings', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $data = json_decode($request->body(), true);

        // Determine requesting party
        if ($data['requesting_party'] === 'artist') {
            $data['artist_id'] = $currentUser['userId'];
        } elseif ($data['requesting_party'] === 'venue') {
            $data['venue_id'] = $currentUser['userId'];
        } else {
            return new JsonResponse(['success' => false, 'message' => 'requesting_party must be set.'], 400);
        }

        $booking = $bookingService->createBooking($data);

        return new JsonResponse(['success' => true, 'data' => $booking], 201);
    } catch (\Throwable $e) {
        $logger->error('Create Booking Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/bookings - List bookings for entity (requires auth)
$router->get('/bookings', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $entityType = $request->query('entity_type') ?? 'artist';
        $status = $request->query('status');
        $limit = (int)($request->query('limit') ?? 50);
        $offset = (int)($request->query('offset') ?? 0);

        $filters = [
            'entity_type' => $entityType,
            'entity_id' => $currentUser['userId']
        ];

        if ($status) $filters['status'] = $status;

        $bookings = $bookingService->listBookings($filters, $limit, $offset);

        return new JsonResponse([
            'success' => true,
            'data' => $bookings,
            'count' => count($bookings)
        ], 200);
    } catch (\Throwable $e) {
        $logger->error('List Bookings Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error listing bookings.'], 400);
    }
});

// GET /api/v1/bookings/:id - Get booking details (requires auth)
$router->get('/bookings/:id', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check
        if ($booking['artist_id'] != $currentUser['userId'] && $booking['venue_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return new JsonResponse(['success' => true, 'data' => $booking], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Booking Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching booking.'], 400);
    }
});

// POST /api/v1/bookings/:id/accept - Accept booking (requires auth)
$router->post('/bookings/:id/accept', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check - opposite party must accept
        if ($booking['requesting_party'] === 'artist' && $booking['venue_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        if ($booking['requesting_party'] === 'venue' && $booking['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = json_decode($request->body(), true);
        $message = $data['message'] ?? null;

        $updated = $bookingService->acceptBooking($bookingId, $message);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Accept Booking Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/bookings/:id/reject - Reject booking (requires auth)
$router->post('/bookings/:id/reject', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check - opposite party must reject
        if ($booking['requesting_party'] === 'artist' && $booking['venue_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        if ($booking['requesting_party'] === 'venue' && $booking['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = json_decode($request->body(), true);
        $reason = $data['reason'] ?? null;

        $updated = $bookingService->rejectBooking($bookingId, $reason);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Reject Booking Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/bookings/:id/counter - Send counter-offer (requires auth)
$router->post('/bookings/:id/counter', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check - either party can send counter
        if ($booking['artist_id'] != $currentUser['userId'] && $booking['venue_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = json_decode($request->body(), true);
        $counterTerms = $data['counter_offer'] ?? [];
        $message = $data['message'] ?? null;

        if (!$message) {
            return new JsonResponse(['success' => false, 'message' => 'message is required.'], 400);
        }

        $updated = $bookingService->counterOffer($bookingId, $counterTerms, $message);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Counter Offer Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/bookings/:id/confirm - Confirm and create event (requires auth)
$router->post('/bookings/:id/confirm', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check - artist confirms (usually)
        if ($booking['artist_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $updated = $bookingService->confirmBooking($bookingId);

        return new JsonResponse(['success' => true, 'data' => $updated], 200);
    } catch (\Throwable $e) {
        $logger->error('Confirm Booking Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// POST /api/v1/bookings/:id/messages - Send message (requires auth)
$router->post('/bookings/:id/messages', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check
        if ($booking['artist_id'] != $currentUser['userId'] && $booking['venue_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $data = json_decode($request->body(), true);
        $message = $data['message'] ?? null;

        if (!$message) {
            return new JsonResponse(['success' => false, 'message' => 'message is required.'], 400);
        }

        // Determine sender type
        $senderType = $booking['artist_id'] == $currentUser['userId'] ? 'artist' : 'venue';
        $senderId = $currentUser['userId'];

        $messages = $bookingService->sendMessage($bookingId, $senderType, $senderId, $message);

        return new JsonResponse(['success' => true, 'data' => $messages], 201);
    } catch (\Throwable $e) {
        $logger->error('Send Message Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// GET /api/v1/bookings/:id/messages - Get messages (requires auth)
$router->get('/bookings/:id/messages', function (Request $request) use ($bookingService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    try {
        $bookingId = $request->param('id');
        $booking = $bookingService->getBooking($bookingId);

        if (!$booking) {
            return new JsonResponse(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        // Authorization check
        if ($booking['artist_id'] != $currentUser['userId'] && $booking['venue_id'] != $currentUser['userId']) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $messages = $bookingService->getMessages($bookingId);

        return new JsonResponse(['success' => true, 'data' => $messages, 'count' => count($messages)], 200);
    } catch (\Throwable $e) {
        $logger->error('Get Messages Error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching messages.'], 400);
    }
});

// GET /api/v1/investments - Get all investments for current user
$router->get('/investments', function (Request $request) use ($investmentService, $tokenSvc) {
    // Auth required
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];

    try {
        $investments = $investmentService->getUserInvestments($userId);

        return new JsonResponse([
            'success' => true,
            'data' => $investments,
            'count' => count($investments)
        ], 200);

    } catch (Exception $e) {
        error_log("Get user investments error: " . $e->getMessage());
        return new JsonResponse([
            'success' => false,
            'message' => 'Internal server error.'
        ], 500);
    }
});

// ═══════════════════════════════════════════════════════════════
// STRIPE CONNECT API ENDPOINTS
// ═══════════════════════════════════════════════════════════════

// POST /api/v1/stripe-connect/onboard - Initiates Stripe Connect onboarding for the current user
$router->post('/stripe-connect/onboard', function (Request $request) use ($stripeConnectService, $tokenSvc, $config, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $userEmail = $currentUser['email'] ?? null; // Assume user email is in JWT claims or fetch from DB

    if (!$userEmail) {
        // Fallback to fetch email from DB if not in claims
        try {
            $pdo = ConnectionFactory::read($config);
            $stmt = $pdo->prepare("SELECT Email FROM ngn_2025.users WHERE Id = ?");
            $stmt->execute([$userId]);
            $userEmail = $stmt->fetchColumn();
            if (!$userEmail) {
                return new JsonResponse(['success' => false, 'message' => 'User email not found.'], 400);
            }
        } catch (\Throwable $e) {
            $logger->error('Error fetching user email for Stripe Connect: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Internal server error.'], 500);
        }
    }

    // Determine return and refresh URLs (adjust as per your frontend structure)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080'; // Default host, should be from config/env
    $baseUrl = "{$protocol}://{$host}";

    // These URLs should ideally point to specific pages in your frontend that handle the redirect
    $returnUrl = $baseUrl . '/dashboard/connect/return'; // Where Stripe redirects after successful onboarding
    $refreshUrl = $baseUrl . '/dashboard/connect/refresh'; // Where Stripe redirects if the link expires

    try {
        $result = $stripeConnectService->createOnboardingLink($userId, $userEmail, $refreshUrl, $returnUrl);
        return new JsonResponse(['success' => true, 'data' => ['url' => $result['url'], 'account_id' => $result['account_id']]], 200);
    } catch (\Throwable $e) {
        $logger->error('Stripe Connect Onboard Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// ============================================================================
// RIGHTS MANAGEMENT API ENDPOINTS (Bible Ch. 14)
// ============================================================================

// GET /api/v1/rights/ledger - Get rights ledger for a track
$router->get('/rights/ledger/:ledgerId', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $ledgerId = $request->param('ledgerId');

    try {
        // Fetch ledger
        $stmt = $pdo->prepare("
            SELECT * FROM cdm_rights_ledger
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$ledgerId]);
        $ledger = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ledger) {
            return new JsonResponse(['success' => false, 'message' => 'Ledger not found.'], 404);
        }

        // Fetch all splits for this ledger
        $stmt = $pdo->prepare("
            SELECT s.*, u.Title as user_name, u.Email as user_email
            FROM cdm_rights_splits s
            LEFT JOIN ngn_2025.users u ON s.user_id = u.Id
            WHERE s.ledger_id = ?
            ORDER BY s.percentage DESC
        ");
        $stmt->execute([$ledgerId]);
        $splits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'ledger' => $ledger,
                'splits' => $splits
            ]
        ], 200);
    } catch (\Throwable $e) {
        error_log("Rights Ledger Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching ledger.'], 500);
    }
});

// POST /api/v1/rights/split/accept - Accept a rights split
$router->post('/rights/split/accept', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $data = json_decode($request->body, true);
    $splitId = $data['split_id'] ?? null;

    if (!$splitId) {
        return new JsonResponse(['success' => false, 'message' => 'split_id required.'], 400);
    }

    try {
        // Verify split belongs to current user
        $stmt = $pdo->prepare("
            SELECT user_id FROM cdm_rights_splits
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$splitId]);
        $split = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$split || $split['user_id'] != $userId) {
            return new JsonResponse(['success' => false, 'message' => 'Split not found or unauthorized.'], 403);
        }

        // Update split acceptance (Bible: Double-opt-in handshake)
        $stmt = $pdo->prepare("
            UPDATE cdm_rights_splits
            SET accepted_at = NOW(),
                ip_address = ?,
                user_agent = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $splitId
        ]);

        return new JsonResponse(['success' => true, 'message' => 'Rights split accepted.'], 200);
    } catch (\Throwable $e) {
        error_log("Rights Split Accept Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error accepting split.'], 500);
    }
});

// POST /api/v1/rights/ledger/dispute - Dispute a rights ledger
$router->post('/rights/ledger/dispute', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];
    $data = json_decode($request->body, true);
    $ledgerId = $data['ledger_id'] ?? null;
    $reason = $data['reason'] ?? null;

    if (!$ledgerId || !$reason) {
        return new JsonResponse(['success' => false, 'message' => 'ledger_id and reason required.'], 400);
    }

    try {
        // Verify user is a party to this ledger
        $stmt = $pdo->prepare("
            SELECT l.id FROM cdm_rights_ledger l
            INNER JOIN cdm_rights_splits s ON l.id = s.ledger_id
            WHERE l.id = ? AND s.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$ledgerId, $userId]);
        $ledger = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ledger) {
            return new JsonResponse(['success' => false, 'message' => 'Ledger not found or unauthorized.'], 403);
        }

        // Update ledger status to disputed
        $stmt = $pdo->prepare("
            UPDATE cdm_rights_ledger
            SET status = 'disputed',
                disputed_reason = ?,
                disputed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $ledgerId]);

        return new JsonResponse(['success' => true, 'message' => 'Ledger disputed. Admin will review.'], 200);
    } catch (\Throwable $e) {
        error_log("Rights Ledger Dispute Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error disputing ledger.'], 500);
    }
});

// GET /api/v1/rights/user - Get all rights ledgers for current user
$router->get('/rights/user', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];

    try {
        // Get all ledgers where user has a split
        $stmt = $pdo->prepare("
            SELECT DISTINCT l.*, COUNT(s.id) as split_count
            FROM cdm_rights_ledger l
            INNER JOIN cdm_rights_splits s ON l.id = s.ledger_id
            WHERE s.user_id = ?
            GROUP BY l.id
            ORDER BY l.updated_at DESC
        ");
        $stmt->execute([$userId]);
        $ledgers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse([
            'success' => true,
            'data' => ['ledgers' => $ledgers]
        ], 200);
    } catch (\Throwable $e) {
        error_log("User Rights Ledgers Error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching ledgers.'], 500);
    }
});

// ============================================================================
// FEATURE FLAG API ENDPOINTS (Bible: GA Cutover & Hyper-Care)
// ============================================================================

// GET /api/v1/admin/feature-flags - Get all feature flags (admin only)
$router->get('/admin/feature-flags', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || !isAdminUser($currentUser)) {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    try {
        $flagService = new \NGN\Lib\Services\FeatureFlagService($pdo);
        $flags = $flagService->getAll();

        return new JsonResponse([
            'success' => true,
            'data' => ['flags' => $flags]
        ], 200);
    } catch (\Throwable $e) {
        error_log("Get feature flags error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching flags.'], 500);
    }
});

// GET /api/v1/admin/feature-flags/:flagName - Get specific feature flag
$router->get('/admin/feature-flags/:flagName', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || !isAdminUser($currentUser)) {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $flagName = $request->param('flagName');

    try {
        $flagService = new \NGN\Lib\Services\FeatureFlagService($pdo);
        $value = $flagService->get($flagName);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'flag_name' => $flagName,
                'value' => $value
            ]
        ], 200);
    } catch (\Throwable $e) {
        error_log("Get feature flag error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching flag.'], 500);
    }
});

// POST /api/v1/admin/feature-flags - Set feature flag (admin only)
$router->post('/admin/feature-flags', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || !isAdminUser($currentUser)) {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $data = json_decode($request->body, true);
    $flagName = $data['flag_name'] ?? null;
    $value = $data['value'] ?? null;
    $reason = $data['reason'] ?? null;

    if (!$flagName || $value === null) {
        return new JsonResponse(['success' => false, 'message' => 'flag_name and value required.'], 400);
    }

    try {
        $flagService = new \NGN\Lib\Services\FeatureFlagService($pdo);
        $success = $flagService->set($flagName, $value, $reason, $currentUser['userId']);

        if ($success) {
            return new JsonResponse([
                'success' => true,
                'message' => "Flag {$flagName} updated to {$value}",
                'data' => ['flag_name' => $flagName, 'value' => $value]
            ], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to set flag.'], 500);
        }
    } catch (\Throwable $e) {
        error_log("Set feature flag error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error setting flag.'], 500);
    }
});

// POST /api/v1/admin/feature-flags/:flagName/increment - Increment numeric flag
$router->post('/admin/feature-flags/:flagName/increment', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || !isAdminUser($currentUser)) {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $flagName = $request->param('flagName');
    $data = json_decode($request->body, true);
    $increment = $data['increment'] ?? 1;
    $maxValue = $data['max_value'] ?? 100;

    try {
        $flagService = new \NGN\Lib\Services\FeatureFlagService($pdo);
        $newValue = $flagService->increment($flagName, $increment, $maxValue);

        if ($newValue !== false) {
            return new JsonResponse([
                'success' => true,
                'message' => "Flag {$flagName} incremented to {$newValue}",
                'data' => ['flag_name' => $flagName, 'new_value' => $newValue]
            ], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to increment flag.'], 500);
        }
    } catch (\Throwable $e) {
        error_log("Increment feature flag error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error incrementing flag.'], 500);
    }
});

// GET /api/v1/admin/feature-flags/:flagName/history - Get flag change history
$router->get('/admin/feature-flags/:flagName/history', function (Request $request) use ($pdo, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || !isAdminUser($currentUser)) {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $flagName = $request->param('flagName');
    $limit = (int)($request->query('limit') ?? 50);

    try {
        $flagService = new \NGN\Lib\Services\FeatureFlagService($pdo);
        $history = $flagService->getHistory($flagName, $limit);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'flag_name' => $flagName,
                'history' => $history
            ]
        ], 200);
    } catch (\Throwable $e) {
        error_log("Get feature flag history error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error fetching history.'], 500);
    }
});

// ============================================================================
// STRIPE CONNECT API (continued)
// ============================================================================

// GET /api/v1/stripe-connect/account-status - Retrieves the current user's Stripe Connect account status
$router->get('/stripe-connect/account-status', function (Request $request) use ($stripeConnectService, $tokenSvc, $logger) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $userId = $currentUser['userId'];

    try {
        $status = $stripeConnectService->getAccountStatus($userId);
        if ($status === null) {
            return new JsonResponse(['success' => true, 'data' => ['connected' => false, 'message' => 'No Stripe Connect account found for user.']], 200);
        }
        return new JsonResponse(['success' => true, 'data' => ['connected' => true, 'account' => $status]], 200);
    } catch (\Throwable $e) {
        $logger->error('Stripe Connect Account Status Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// ============================================================================
// STATION CONTENT APIS (BYOS, Playlists, Tier, Listener Requests)
// ============================================================================

// POST /api/v1/stations/:stationId/content/upload - BYOS file upload
$router->post('/stations/:stationId/content/upload', function (Request $request) use ($stationContentService, $stationTierService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $stationId = (int)$request->param('stationId');

    try {
        // Verify station ownership (simplified - in production, check if user owns station)
        // TODO: Add ownership verification

        // Get file from request
        if (!isset($_FILES['file'])) {
            return new JsonResponse(['success' => false, 'message' => 'File is required.'], 400);
        }

        // Get metadata from request
        $metadata = [
            'title' => $request->param('title'),
            'artist_name' => $request->param('artist_name')
        ];

        // Get indemnity acceptance from request body
        $body = $request->json();
        $indemnityAccepted = isset($body['indemnity_accepted']) && $body['indemnity_accepted'] === true;

        // Upload content
        $result = $stationContentService->uploadContent(
            $stationId,
            $_FILES['file'],
            $metadata,
            $indemnityAccepted
        );

        return new JsonResponse($result, $result['success'] ? 201 : 400);

    } catch (\InvalidArgumentException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\RuntimeException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        $logger->error('BYOS upload failed: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Upload failed.'], 500);
    }
});

// GET /api/v1/stations/:stationId/content - List station content
$router->get('/stations/:stationId/content', function (Request $request) use ($stationContentService) {
    $stationId = (int)$request->param('stationId');
    $status = $request->query('status');
    $page = (int)($request->query('page') ?? 1);
    $perPage = (int)($request->query('per_page') ?? 20);

    try {
        $result = $stationContentService->listContent($stationId, $status, $page, $perPage);
        return new JsonResponse($result, 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to list content.'], 500);
    }
});

// GET /api/v1/stations/:stationId/content/:contentId - Get content details
$router->get('/stations/:stationId/content/:contentId', function (Request $request) use ($stationContentService) {
    $stationId = (int)$request->param('stationId');
    $contentId = (int)$request->param('contentId');

    try {
        $content = $stationContentService->getContent($contentId, $stationId);
        if (!$content) {
            return new JsonResponse(['success' => false, 'message' => 'Content not found.'], 404);
        }
        return new JsonResponse(['success' => true, 'data' => $content], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to get content.'], 500);
    }
});

// DELETE /api/v1/stations/:stationId/content/:contentId - Delete content
$router->delete('/stations/:stationId/content/:contentId', function (Request $request) use ($stationContentService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $stationId = (int)$request->param('stationId');
    $contentId = (int)$request->param('contentId');

    try {
        $success = $stationContentService->deleteContent($contentId, $stationId);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to delete content.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Content deleted.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to delete content.'], 500);
    }
});

// ============================================================================
// STATION PLAYLIST APIS
// ============================================================================

// POST /api/v1/stations/:stationId/playlists - Create playlist
$router->post('/stations/:stationId/playlists', function (Request $request) use ($stationPlaylistService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $stationId = (int)$request->param('stationId');
    $body = $request->json();

    try {
        $result = $stationPlaylistService->createPlaylist(
            $stationId,
            $body['title'] ?? '',
            $body['items'] ?? [],
            $body['geo_restrictions'] ?? null,
            $body['schedule'] ?? null
        );
        return new JsonResponse($result, $result['success'] ? 201 : 400);
    } catch (\InvalidArgumentException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to create playlist.'], 500);
    }
});

// GET /api/v1/stations/:stationId/playlists - List playlists
$router->get('/stations/:stationId/playlists', function (Request $request) use ($stationPlaylistService) {
    $stationId = (int)$request->param('stationId');
    $page = (int)($request->query('page') ?? 1);
    $perPage = (int)($request->query('per_page') ?? 20);

    try {
        $result = $stationPlaylistService->listPlaylists($stationId, $page, $perPage);
        return new JsonResponse($result, 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to list playlists.'], 500);
    }
});

// GET /api/v1/playlists/:playlistId - Get playlist details
$router->get('/playlists/:playlistId', function (Request $request) use ($stationPlaylistService) {
    $playlistId = (int)$request->param('playlistId');

    try {
        $playlist = $stationPlaylistService->getPlaylist($playlistId);
        if (!$playlist) {
            return new JsonResponse(['success' => false, 'message' => 'Playlist not found.'], 404);
        }
        return new JsonResponse(['success' => true, 'data' => $playlist], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to get playlist.'], 500);
    }
});

// PUT /api/v1/playlists/:playlistId - Update playlist
$router->put('/playlists/:playlistId', function (Request $request) use ($stationPlaylistService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $playlistId = (int)$request->param('playlistId');
    $body = $request->json();

    try {
        $playlist = $stationPlaylistService->getPlaylist($playlistId);
        if (!$playlist) {
            return new JsonResponse(['success' => false, 'message' => 'Playlist not found.'], 404);
        }

        $success = $stationPlaylistService->updatePlaylist($playlistId, $playlist['station_id'], $body);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to update playlist.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Playlist updated.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to update playlist.'], 500);
    }
});

// PUT /api/v1/playlists/:playlistId/items - Reorder/modify items
$router->put('/playlists/:playlistId/items', function (Request $request) use ($stationPlaylistService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $playlistId = (int)$request->param('playlistId');
    $body = $request->json();
    $items = $body['items'] ?? [];

    try {
        $success = $stationPlaylistService->reorderItems($playlistId, $items);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to reorder items.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Items reordered.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to reorder items.'], 500);
    }
});

// DELETE /api/v1/playlists/:playlistId - Delete playlist
$router->delete('/playlists/:playlistId', function (Request $request) use ($stationPlaylistService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $playlistId = (int)$request->param('playlistId');

    try {
        $playlist = $stationPlaylistService->getPlaylist($playlistId);
        if (!$playlist) {
            return new JsonResponse(['success' => false, 'message' => 'Playlist not found.'], 404);
        }

        $success = $stationPlaylistService->deletePlaylist($playlistId, $playlist['station_id']);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to delete playlist.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Playlist deleted.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to delete playlist.'], 500);
    }
});

// POST /api/v1/playlists/:id/items - Add item to playlist
$router->post('/playlists/:id/items', function (Request $request) use ($stationPlaylistService, $tokenSvc, $pdo) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $playlistId = (int)$request->param('id');
    $body = json_decode($request->body(), true);
    $trackId = $body['track_id'] ?? null;
    $trackType = $body['track_type'] ?? null;

    if (!$trackId || !$trackType) {
        return new JsonResponse(['success' => false, 'message' => 'Track ID and type are required.'], 400);
    }

    try {
        // Check playlist ownership
        $stmt = $pdo->prepare("SELECT s.id from `ngn_2025`.`stations` s JOIN `ngn_2025`.`users` u ON s.user_id = u.id WHERE u.id = ?");
        $stmt->execute([$currentUser['userId']]);
        $station = $stmt->fetch();

        if (!$station) {
             return new JsonResponse(['success' => false, 'message' => 'Station not found for user.'], 403);
        }

        $playlist = $stationPlaylistService->getPlaylist($playlistId);
        if (!$playlist || $playlist['station_id'] !== $station['id']) {
            return new JsonResponse(['success' => false, 'message' => 'Playlist not found or you do not have permission to edit it.'], 404);
        }

        $newItem = [];
        if ($trackType === 'track') {
            $newItem['track_id'] = (int)$trackId;
        } elseif ($trackType === 'station_content') {
            $newItem['station_content_id'] = (int)$trackId;
        }
 else {
            return new JsonResponse(['success' => false, 'message' => 'Invalid track type.'], 400);
        }

        $newItem['position'] = count($playlist['items']);

        if ($stationPlaylistService->addPlaylistItems($playlistId, [$newItem])) {
            return new JsonResponse(['success' => true, 'message' => 'Track added to playlist.'], 200);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to add track.'], 500);
        }

    } catch (\Throwable $e) {
        error_log('Add playlist item error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Failed to add track.'], 500);
    }
});

// PUT /api/v1/playlists/:id/items/reorder - Reorder playlist items
$router->put('/playlists/:id/items/reorder', function (Request $request) use ($stationPlaylistService, $tokenSvc, $pdo) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $playlistId = (int)$request->param('id');
    $body = json_decode($request->body(), true);
    $items = $body['items'] ?? [];

    if (empty($items)) {
        return new JsonResponse(['success' => false, 'message' => 'Items are required.'], 400);
    }

    try {
        // Check playlist ownership
        $stmt = $pdo->prepare("SELECT s.id from `ngn_2025`.`stations` s JOIN `ngn_2025`.`users` u ON s.user_id = u.id WHERE u.id = ?");
        $stmt->execute([$currentUser['userId']]);
        $station = $stmt->fetch();

        if (!$station) {
             return new JsonResponse(['success' => false, 'message' => 'Station not found for user.'], 403);
        }

        $playlist = $stationPlaylistService->getPlaylist($playlistId);
        if (!$playlist || $playlist['station_id'] !== $station['id']) {
            return new JsonResponse(['success' => false, 'message' => 'Playlist not found or you do not have permission to edit it.'], 404);
        }

        if ($stationPlaylistService->reorderItems($playlistId, $items)) {
            return new JsonResponse(['success' => true, 'message' => 'Playlist order updated successfully.']);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Failed to update playlist order.']);
        }

    } catch (\Throwable $e) {
        error_log('Reorder playlist items error: ' . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Failed to reorder items.'], 500);
    }
});



// ============================================================================
// STATION TIER APIs
// ============================================================================

// GET /api/v1/stations/:stationId/tier - Get current tier info
$router->get('/stations/:stationId/tier', function (Request $request) use ($stationTierService) {
    $stationId = (int)$request->param('stationId');

    try {
        $tier = $stationTierService->getStationTier($stationId);
        if (!$tier) {
            return new JsonResponse(['success' => false, 'message' => 'Tier not found.'], 404);
        }

        // Add usage statistics
        $tier['usage'] = [
            'byos_tracks' => 0, // TODO: Calculate from database
            'playlists' => 0    // TODO: Calculate from database
        ];

        return new JsonResponse(['success' => true, 'data' => $tier], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to get tier info.'], 500);
    }
});

// GET /api/v1/station-tiers - Get all available tiers
$router->get('/station-tiers', function (Request $request) use ($stationTierService) {
    try {
        $comparison = $stationTierService->getTierComparison();
        return new JsonResponse(['success' => true, 'data' => $comparison], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to get tiers.'], 500);
    }
});

// ============================================================================
// LISTENER REQUEST APIs
// ============================================================================

// POST /api/v1/stations/:stationId/requests - Submit listener request
$router->post('/stations/:stationId/requests', function (Request $request) use ($listenerRequestService, $tokenSvc) {
    $stationId = (int)$request->param('stationId');
    $body = $request->json();
    $currentUser = getCurrentUser($tokenSvc, $request);
    $userId = $currentUser['userId'] ?? null;

    try {
        // Get user IP for anonymous tracking
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $result = $listenerRequestService->submitRequest(
            $stationId,
            $userId,
            $body['request_type'] ?? 'song',
            $body['data'] ?? [],
            $ipAddress,
            $userAgent
        );

        return new JsonResponse($result, $result['success'] ? 201 : 400);
    } catch (\InvalidArgumentException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\RuntimeException $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 429);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to submit request.'], 500);
    }
});

// GET /api/v1/stations/:stationId/requests - Get request queue (DJ view)
$router->get('/stations/:stationId/requests', function (Request $request) use ($listenerRequestService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $stationId = (int)$request->param('stationId');
    $status = $request->query('status');
    $page = (int)($request->query('page') ?? 1);
    $perPage = (int)($request->query('per_page') ?? 20);

    try {
        $result = $listenerRequestService->listRequests($stationId, $status, $page, $perPage);
        $stats = $listenerRequestService->getStats($stationId);
        $result['stats'] = $stats;
        return new JsonResponse($result, 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to get requests.'], 500);
    }
});

// PUT /api/v1/stations/:stationId/requests/:requestId/approve - DJ approves request
$router->put('/stations/:stationId/requests/:requestId/approve', function (Request $request) use ($listenerRequestService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $stationId = (int)$request->param('stationId');
    $requestId = (int)$request->param('requestId');

    try {
        $success = $listenerRequestService->approveRequest($requestId, $stationId, $currentUser['userId']);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to approve request.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Request approved.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to approve request.'], 500);
    }
});

// PUT /api/v1/stations/:stationId/requests/:requestId/reject - DJ rejects request
$router->put('/stations/:stationId/requests/:requestId/reject', function (Request $request) use ($listenerRequestService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser) {
        return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $stationId = (int)$request->param('stationId');
    $requestId = (int)$request->param('requestId');
    $body = $request->json();
    $reason = $body['reason'] ?? 'No reason provided';

    try {
        $success = $listenerRequestService->rejectRequest($requestId, $stationId, $reason);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to reject request.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Request rejected.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to reject request.'], 500);
    }
});

// ============================================================================
// ADMIN APIS (Station Content Review)
// ============================================================================

// GET /api/v1/admin/station-content/review-queue - Pending content
$router->get('/admin/station-content/review-queue', function (Request $request) use ($stationContentService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    // TODO: Implement admin-only query to fetch ALL pending content across stations
    return new JsonResponse(['success' => true, 'data' => [], 'message' => 'Not yet implemented'], 501);
});

// PUT /api/v1/admin/station-content/:contentId/approve - Approve content
$router->put('/admin/station-content/:contentId/approve', function (Request $request) use ($stationContentService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $contentId = (int)$request->param('contentId');

    try {
        $success = $stationContentService->approveContent($contentId, $currentUser['userId']);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to approve content.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Content approved.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to approve content.'], 500);
    }
});

// PUT /api/v1/admin/station-content/:contentId/reject - Reject content
$router->put('/admin/station-content/:contentId/reject', function (Request $request) use ($stationContentService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $contentId = (int)$request->param('contentId');
    $body = $request->json();
    $reason = $body['reason'] ?? 'Rejected by admin';

    try {
        $success = $stationContentService->rejectContent($contentId, $currentUser['userId'], $reason);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to reject content.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Content rejected.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to reject content.'], 500);
    }
});

// PUT /api/v1/admin/station-content/:contentId/takedown - DMCA takedown
$router->put('/admin/station-content/:contentId/takedown', function (Request $request) use ($stationContentService, $tokenSvc) {
    $currentUser = getCurrentUser($tokenSvc, $request);
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        return new JsonResponse(['success' => false, 'message' => 'Admin access required.'], 403);
    }

    $contentId = (int)$request->param('contentId');
    $body = $request->json();
    $reason = $body['reason'] ?? 'Content takedown';

    try {
        $success = $stationContentService->takedownContent($contentId, $currentUser['userId'], $reason);
        if (!$success) {
            return new JsonResponse(['success' => false, 'message' => 'Failed to takedown content.'], 400);
        }
        return new JsonResponse(['success' => true, 'message' => 'Content taken down.'], 200);
    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => 'Failed to takedown content.'], 500);
    }
});

// --- Profile Claim System Routes ---

// POST /api/v1/search/profiles - Search for claimable profiles
$router->post('/search/profiles', function (Request $request) use ($config, $logger, $pdo) {
    try {
        $body = json_decode($request->body(), true);
        $query = trim($body['query'] ?? '');
        $entityTypes = $body['entity_types'] ?? ['artist', 'label', 'venue', 'station'];

        if (empty($query)) {
            return new JsonResponse(['success' => false, 'message' => 'Search query is required.'], 400);
        }

        // Validate entity types
        $validTypes = ['artist', 'label', 'venue', 'station'];
        $entityTypes = array_filter($entityTypes, function($t) use ($validTypes) {
            return in_array($t, $validTypes);
        });

        if (empty($entityTypes)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid entity types.'], 400);
        }

        $searchTerm = '%' . $query . '%';
        $results = [];

        // Search artists
        if (in_array('artist', $entityTypes)) {
            $stmt = $pdo->prepare("
                SELECT
                    id, slug, name, image_url, 'artist' as entity_type,
                    user_id, claimed, email
                FROM `ngn_2025`.`artists`
                WHERE (
                    name LIKE ? OR
                    slug LIKE ? OR
                    email LIKE ? OR
                    facebook_url LIKE ? OR
                    instagram_url LIKE ?
                )
                LIMIT 100
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Search labels
        if (in_array('label', $entityTypes)) {
            $stmt = $pdo->prepare("
                SELECT
                    id, slug, name, image_url, 'label' as entity_type,
                    user_id, claimed, email
                FROM `ngn_2025`.`labels`
                WHERE (
                    name LIKE ? OR
                    slug LIKE ? OR
                    email LIKE ? OR
                    facebook_url LIKE ? OR
                    instagram_url LIKE ?
                )
                LIMIT 100
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Search venues
        if (in_array('venue', $entityTypes)) {
            $stmt = $pdo->prepare("
                SELECT
                    id, slug, name, image_url, city, region, 'venue' as entity_type,
                    user_id, claimed, email
                FROM `ngn_2025`.`venues`
                WHERE (
                    name LIKE ? OR
                    slug LIKE ? OR
                    city LIKE ? OR
                    email LIKE ? OR
                    facebook_url LIKE ?
                )
                LIMIT 100
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Search stations
        if (in_array('station', $entityTypes)) {
            $stmt = $pdo->prepare("
                SELECT
                    id, slug, name, call_sign, image_url, region, format, 'station' as entity_type,
                    user_id, claimed, email
                FROM `ngn_2025`.`stations`
                WHERE (
                    name LIKE ? OR
                    slug LIKE ? OR
                    call_sign LIKE ? OR
                    email LIKE ? OR
                    facebook_url LIKE ?
                )
                LIMIT 100
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Remove duplicates and return only unclaimed profiles
        $uniqueResults = [];
        $seen = [];
        foreach ($results as $result) {
            $key = $result['entity_type'] . '_' . $result['id'];
            if (!isset($seen[$key])) {
                $result['claimed'] = (int)$result['claimed'] === 1 || $result['user_id'] !== null;
                $uniqueResults[] = $result;
                $seen[$key] = true;
            }
        }

        // Sort by entity type and name
        usort($uniqueResults, function($a, $b) {
            if ($a['entity_type'] !== $b['entity_type']) {
                return strcmp($a['entity_type'], $b['entity_type']);
            }
            return strcmp($a['name'], $b['name']);
        });

        return new JsonResponse([
            'success' => true,
            'data' => array_slice($uniqueResults, 0, 20)
        ], 200);

    } catch (\Throwable $e) {
        $logger->error("Profile search error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error searching profiles.'], 500);
    }
});

// GET /api/v1/claims/verify/:code - Verify email code for claim
$router->get('/claims/verify/:code', function (Request $request) use ($config, $logger, $pdo) {
    try {
        $code = $request->param('code');

        if (empty($code)) {
            return new JsonResponse(['success' => false, 'message' => 'Verification code is required.'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT * FROM `ngn_2025`.`pending_claims`
            WHERE verification_code = ? AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid or expired verification code.'], 404);
        }

        if ($claim['email_verified'] === 1) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Email already verified.',
                'data' => ['status' => 'already_verified']
            ], 200);
        }

        // Mark email as verified
        $stmt = $pdo->prepare("
            UPDATE `ngn_2025`.`pending_claims`
            SET email_verified = 1, email_verified_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$claim['id']]);

        $logger->info("Email verified via API for claim ID {$claim['id']}");

        return new JsonResponse([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => ['claim_id' => $claim['id']]
        ], 200);

    } catch (\Throwable $e) {
        $logger->error("Email verification API error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error verifying email.'], 500);
    }
});

// GET /api/v1/claims/list - List claims (admin only)
$router->get('/claims/list', function (Request $request) use ($config, $logger, $pdo, $tokenSvc) {
    try {
        $user = getCurrentUser($tokenSvc, $request);
        if (!$user || $user['role'] !== 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $status = $request->query('status');
        $entityType = $request->query('entity_type');
        $page = (int)($request->query('page') ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $query = "SELECT * FROM `ngn_2025`.`pending_claims` WHERE 1=1";
        $params = [];

        if ($status && in_array($status, ['pending', 'approved', 'rejected', 'expired'])) {
            $query .= " AND status = ?";
            $params[] = $status;
        }

        if ($entityType && in_array($entityType, ['artist', 'label', 'venue', 'station'])) {
            $query .= " AND entity_type = ?";
            $params[] = $entityType;
        }

        // Get total count
        $countStmt = $pdo->prepare(str_replace('SELECT *', 'SELECT COUNT(*) as count', $query));
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Get paginated results
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return new JsonResponse([
            'success' => true,
            'data' => $claims,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ], 200);

    } catch (\Throwable $e) {
        $logger->error("Claims list error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error loading claims.'], 500);
    }
});

// POST /api/v1/claims/approve/:id - Approve claim (admin only)
$router->post('/claims/approve/:id', function (Request $request) use ($config, $logger, $pdo, $tokenSvc) {
    try {
        $user = getCurrentUser($tokenSvc, $request);
        if (!$user || $user['role'] !== 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $claimId = (int)$request->param('id');
        $body = json_decode($request->body(), true);
        $adminNotes = trim($body['admin_notes'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`pending_claims` WHERE id = ?");
        $stmt->execute([$claimId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            return new JsonResponse(['success' => false, 'message' => 'Claim not found.'], 404);
        }

        if ($claim['status'] !== 'pending') {
            return new JsonResponse(['success' => false, 'message' => 'Claim is not pending.'], 400);
        }

        // Check if user exists by email
        $userStmt = $pdo->prepare("SELECT * FROM `nextgennoise`.`users` WHERE Email = ? LIMIT 1");
        $userStmt->execute([$claim['claimant_email']]);
        $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);

        $userId = null;
        if ($existingUser) {
            $userId = $existingUser['Id'];
        } else {
            // Create new user
            $tempPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            $createUserStmt = $pdo->prepare("
                INSERT INTO `nextgennoise`.`users` (Email, DisplayName, PasswordHash, IsActive, CreatedAt, UpdatedAt)
                VALUES (?, ?, ?, 1, NOW(), NOW())
            ");
            $createUserStmt->execute([$claim['claimant_email'], $claim['claimant_name'], $passwordHash]);
            $userId = $pdo->lastInsertId();

            // TODO: Send welcome email with temp password
            $logger->info("New user created for claim: ID=$userId, Email={$claim['claimant_email']}");
        }

        // Update entity to link user
        $tableMap = ['artist' => 'artists', 'label' => 'labels', 'venue' => 'venues', 'station' => 'stations'];
        $table = $tableMap[$claim['entity_type']];

        $updateEntity = $pdo->prepare("
            UPDATE `ngn_2025`.`$table`
            SET user_id = ?, claimed = 1, verified = 1, updated_at = NOW()
            WHERE id = ?
        ");
        $updateEntity->execute([$userId, $claim['entity_id']]);

        // Update claim status
        $updateClaim = $pdo->prepare("
            UPDATE `ngn_2025`.`pending_claims`
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(),
                admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateClaim->execute([$user['userId'], $adminNotes, $claimId]);

        $logger->info("Claim approved: ID=$claimId, User=$userId");

        return new JsonResponse([
            'success' => true,
            'message' => 'Claim approved successfully.',
            'data' => ['claim_id' => $claimId, 'user_id' => $userId]
        ], 200);

    } catch (\Throwable $e) {
        $logger->error("Claim approval error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error approving claim.'], 500);
    }
});

// POST /api/v1/claims/reject/:id - Reject claim (admin only)
$router->post('/claims/reject/:id', function (Request $request) use ($config, $logger, $pdo, $tokenSvc) {
    try {
        $user = getCurrentUser($tokenSvc, $request);
        if (!$user || $user['role'] !== 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $claimId = (int)$request->param('id');
        $body = json_decode($request->body(), true);
        $adminNotes = trim($body['admin_notes'] ?? '');

        if (empty($adminNotes)) {
            return new JsonResponse(['success' => false, 'message' => 'Reason for rejection is required.'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM `ngn_2025`.`pending_claims` WHERE id = ?");
        $stmt->execute([$claimId]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            return new JsonResponse(['success' => false, 'message' => 'Claim not found.'], 404);
        }

        if ($claim['status'] !== 'pending') {
            return new JsonResponse(['success' => false, 'message' => 'Claim is not pending.'], 400);
        }

        // Update claim status
        $stmt = $pdo->prepare("
            UPDATE `ngn_2025`.`pending_claims`
            SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(),
                admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['userId'], $adminNotes, $claimId]);

        // TODO: Send rejection email
        $logger->info("Claim rejected: ID=$claimId, Reason=$adminNotes");

        return new JsonResponse([
            'success' => true,
            'message' => 'Claim rejected successfully.',
            'data' => ['claim_id' => $claimId]
        ], 200);

    } catch (\Throwable $e) {
        $logger->error("Claim rejection error: " . $e->getMessage());
        return new JsonResponse(['success' => false, 'message' => 'Error rejecting claim.'], 500);
    }
});

// --- Dispatching the router with P95 timing middleware ---
try {
    // Create Request from superglobals
    $request = new Request();

    // Get current user for metrics tracking
    $currentUser = getCurrentUser($tokenSvc, $request);
    $userId = $currentUser['userId'] ?? null;

    // Dispatch through middleware if available, otherwise dispatch directly
    if ($timingMiddleware) {
        $response = $timingMiddleware->wrap($request, $userId, function () use ($router, $request) {
            $handler = $router->dispatch($request);
            if ($handler) {
                $response = $handler($request);
                // Ensure we return a JsonResponse
                if ($response instanceof JsonResponse) {
                    return $response;
                } elseif ($response instanceof Response) {
                    // Convert Response to JsonResponse if needed
                    return new JsonResponse(['success' => true], 200);
                }
            }
            // Route not found
            return new JsonResponse(['success' => false, 'message' => 'Not Found'], 404);
        });
    } else {
        // Dispatch directly without middleware
        $handler = $router->dispatch($request);
        if ($handler) {
            $response = $handler($request);
            if (!($response instanceof JsonResponse || $response instanceof Response)) {
                $response = new JsonResponse(['success' => true], 200);
            }
        } else {
            $response = new JsonResponse(['success' => false, 'message' => 'Not Found'], 404);
        }
    }

    // Send the response
    $response->send();

} catch (\Throwable $e) {
    // If something goes wrong, log it and return a generic server error
    error_log('API Dispatch Error: ' . $e->getMessage());
    if ($config && $config->appDebug()) {
        Response::json(['error' => 'Internal Server Error', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    } else {
        Response::json(['error' => 'Internal Server Error'], 500);
    }
}