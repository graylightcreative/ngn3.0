<?php
/**
 * NGN Next Task Identifier
 * Reads progress-master.json and outputs the next incomplete task.
 * Bible Ref: Chapter 7 - Master Roadmap
 */

$root = dirname(__DIR__);
$roadmapPath = $root . '/storage/plan/progress-master.json';

if (!file_exists($roadmapPath)) {
    echo "\033[91m✗ Roadmap file not found: {$roadmapPath}\033[0m
";
    exit(1);
}

$data = json_decode(file_get_contents($roadmapPath), true);
if (!$data) {
    echo "\033[91m✗ Failed to decode roadmap JSON.\033[0m
";
    exit(1);
}

$nextLandmark = $data['next_landmark'] ?? '2.2.0';
$milestoneKey = str_replace('.', '_', $nextLandmark);
$milestone = $data['milestones'][$milestoneKey] ?? null;

if (!$milestone) {
    echo "\033[93m⚠ Milestone {$nextLandmark} not found in roadmap.\033[0m
";
    exit(0);
}

echo "
\033[1;36m================================================================================\033[0m
";
echo "\033[1;36mNGN NEXT OBJECTIVE // v{$nextLandmark}\033[0m
";
echo "\033[1;36m================================================================================\033[0m
";

$found = false;
foreach ($milestone['categories'] as $category) {
    foreach ($cat['tasks'] ?? [] as $task) {
        // progress-master.json structure check
    }
}

// Re-scanning structure from progress-master.json
foreach ($milestone['categories'] as $category) {
    foreach ($category['tasks'] as $task) {
        if (($task['status'] ?? '') !== 'Done') {
            echo "
\033[1;32mCATEGORY:\033[0m " . strtoupper($category['name']) . "
";
            echo "\033[1;32mTASK:\033[0m     " . $task['description'] . "
";
            
            if (!empty($task['sub_tasks'])) {
                echo "\033[1;32mSUB-TASKS:\033[0m
";
                foreach ($task['sub_tasks'] as $sub) {
                    echo "  - {$sub}
";
                }
            }
            
            echo "
\033[1;36m================================================================================\033[0m
";
            $found = true;
            break 2;
        }
    }
}

if (!$found) {
    echo "\033[92m✓ All tasks for milestone v{$nextLandmark} are complete!\033[0m
";
}
