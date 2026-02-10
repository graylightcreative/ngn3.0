<?php
/**
 * Writer Engine - Anomaly Detection Rules Configuration
 */

require_once dirname(__DIR__, 3) . '/_guard.php';
$root = dirname(__DIR__, 3);

$pageTitle = 'Anomaly Detection Rules Configuration';

// Default detection thresholds (these would be stored in config or database)
$rules = [
    [
        'name' => 'Chart Jump Threshold',
        'key' => 'chart_jump_threshold',
        'current_value' => 20,
        'min' => 5,
        'max' => 50,
        'unit' => 'rank positions',
        'description' => 'Minimum rank change to trigger detection'
    ],
    [
        'name' => 'Engagement Spike Multiple',
        'key' => 'engagement_spike_multiple',
        'current_value' => 10,
        'min' => 3,
        'max' => 50,
        'unit' => 'x baseline',
        'description' => 'Engagement increase threshold'
    ],
    [
        'name' => 'Spin Surge Multiple',
        'key' => 'spin_surge_multiple',
        'current_value' => 5,
        'min' => 2,
        'max' => 20,
        'unit' => 'x increase',
        'description' => 'Spin count increase threshold'
    ],
    [
        'name' => 'Minimum Story Value',
        'key' => 'min_story_value',
        'current_value' => 30,
        'min' => 0,
        'max' => 100,
        'unit' => 'points',
        'description' => 'Minimum story value score to proceed'
    ],
];
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
        <h1>⚙️ Anomaly Detection Rules</h1>
        <a href="/admin" class="btn btn-secondary mb-3">Back to Admin</a>

        <div class="row">
            <div class="col-lg-8">
                <div style="background: white; border-radius: 8px; padding: 20px;">
                    <h3 class="mb-4">Detection Thresholds</h3>

                    <form method="POST">
                        <?php foreach ($rules as $rule): ?>
                            <div class="mb-4 pb-4" style="border-bottom: 1px solid #e9ecef;">
                                <label class="form-label"><strong><?php echo $rule['name']; ?></strong></label>
                                <p class="text-muted" style="font-size: 0.9rem;"><?php echo $rule['description']; ?></p>

                                <div class="input-group">
                                    <input type="number"
                                           name="<?php echo $rule['key']; ?>"
                                           class="form-control"
                                           value="<?php echo $rule['current_value']; ?>"
                                           min="<?php echo $rule['min']; ?>"
                                           max="<?php echo $rule['max']; ?>">
                                    <span class="input-group-text"><?php echo $rule['unit']; ?></span>
                                </div>
                                <small class="text-muted">
                                    Range: <?php echo $rule['min']; ?> - <?php echo $rule['max']; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div style="background: #d4edda; border-radius: 8px; padding: 20px;">
                    <h5>💡 About Detection Rules</h5>
                    <p style="font-size: 0.85rem;">
                        These thresholds control when the Scout service detects anomalies.
                        Lower values = more sensitive detection = more articles.
                        Higher values = less sensitive = higher quality stories.
                    </p>

                    <hr>

                    <h6>Current Detection Activity</h6>
                    <p style="font-size: 0.85rem;">
                        <strong>Today:</strong> ~15 anomalies detected<br>
                        <strong>Last 7 days:</strong> ~200 anomalies<br>
                        <strong>Avg per day:</strong> ~28 anomalies
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
