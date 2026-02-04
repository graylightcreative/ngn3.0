<?php

use NGN\Lib\Http\{Request, Response, JsonResponse};
use NGN\Lib\DB\Migrator;
use NGN\Lib\Config;
use NGN\Lib\Env;

if (!isset($router) || !isset($config)) {
    // This file should be included from api/v1/index.php where $router and $config are set.
    return;
}

// GET /api/v1/admin/upgrade/checks
$router->get('/admin/upgrade/checks', function (Request $request) use ($config) {
    try {
        $migrator = new Migrator($config);
        $status = $migrator->status();
        $available = $migrator->getAvailableCategorized();

        Response::json([
            'data' => [
                'checks' => [
                    'db_connection' => 'ok',
                    'migrations' => [
                        'applied' => $status['applied'],
                        'pending' => $status['pending'],
                        'available_categorized' => $available
                    ]
                ]
            ]
        ]);
    } catch (Exception $e) {
        // Use the correct static Response::json method
        Response::json(['error' => 'Upgrade checks failed: ' . $e->getMessage()], 500);
    }
});

// POST /api/v1/admin/upgrade/migrate
$router->post('/admin/upgrade/migrate', function (Request $request) use ($config) {
    try {
        $migrator = new Migrator($config);
        $applied = $migrator->applyPending();
        Response::json(['data' => ['applied' => $applied]]);
    } catch (Exception $e) {
        error_log('Migration failed: ' . $e->getMessage());
        Response::json(['error' => 'Migration failed: ' . $e->getMessage()], 500);
    }
});

// Placeholder for /api/v1/admin/upgrade/backups
$router->post('/admin/upgrade/backups', function (Request $request) use ($config) {
    return new JsonResponse(['success' => true, 'data' => ['message' => 'Backup simulation complete.']]);
});

// Placeholder for /api/v1/admin/upgrade/verify
$router->get('/admin/upgrade/verify', function (Request $request) use ($config) {
    return new JsonResponse(['success' => true, 'data' => ['verify' => ['etl' => ['status' => 'ok']]]]);
});

// Placeholder for /api/v1/admin/maintenance
$router->put('/admin/maintenance', function (Request $request) use ($config) {
    // In a real app, this would update a persistent config.
    // For now, we just acknowledge the request.
    $body = json_decode($request->body(), true);
    $enabled = $body['enabled'] ?? false;
    return new JsonResponse(['success' => true, 'data' => ['enabled' => $enabled]]);
});

// Placeholder for /api/v1/admin/progress
$router->get('/admin/progress', function (Request $request) use ($config) {
    // Return a simplified version of progress.json for the UI to consume
    return new JsonResponse(['success' => true, 'data' => ['milestones' => []]]);
});

