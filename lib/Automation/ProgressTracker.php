<?php
namespace NGN\Lib\Automation;

/**
 * ProgressTracker - Manages progress JSON files
 *
 * Handles reading, updating, and auto-detecting completions in
 * progress-beta-X.X.X.json files and the master progress.json
 */
class ProgressTracker
{
    private string $projectRoot;
    private string $version;
    private array $masterProgress = [];
    private array $versionProgress = [];
    private string $progressDir;

    public function __construct(string $projectRoot, string $version = '')
    {
        $this->projectRoot = $projectRoot;
        $this->version = $version;
        $this->progressDir = $projectRoot . '/storage/plan';

        if (!is_dir($this->progressDir)) {
            mkdir($this->progressDir, 0755, true);
        }
    }

    /**
     * Load master progress.json
     */
    public function loadMasterProgress(): void
    {
        $file = $this->progressDir . '/progress.json';
        if (file_exists($file)) {
            $this->masterProgress = json_decode(file_get_contents($file), true) ?? [];
        }
    }

    /**
     * Load version-specific progress file
     */
    public function loadVersionProgress(string $version): void
    {
        $this->version = $version;
        $file = $this->progressDir . '/progress-beta-' . $version . '.json';
        if (file_exists($file)) {
            $this->versionProgress = json_decode(file_get_contents($file), true) ?? [];
        }
    }

    /**
     * Get current version progress data
     */
    public function getVersionProgress(): array
    {
        return $this->versionProgress;
    }

    /**
     * Get master progress data
     */
    public function getMasterProgress(): array
    {
        return $this->masterProgress;
    }

    /**
     * Update task status in version-specific progress
     */
    public function updateTaskStatus(string $taskId, string $status): void
    {
        if (!isset($this->versionProgress['tasks'])) {
            $this->versionProgress['tasks'] = [];
        }

        foreach ($this->versionProgress['tasks'] as &$task) {
            if ($task['id'] === $taskId) {
                $task['status'] = $status;
                break;
            }
        }
    }

    /**
     * Auto-detect completed tasks based on file existence
     * Returns array of newly completed tasks
     */
    public function autoDetectCompletions(array $fileChecks): array
    {
        $completed = [];

        if (!isset($this->versionProgress['tasks'])) {
            return $completed;
        }

        foreach ($this->versionProgress['tasks'] as &$task) {
            $taskId = $task['id'];

            // Check if task is already completed
            if ($task['status'] === 'completed') {
                continue;
            }

            // Check if any files exist for this task
            if (isset($fileChecks[$taskId])) {
                $allFilesExist = true;
                foreach ($fileChecks[$taskId] as $filePath) {
                    $fullPath = $this->projectRoot . '/' . $filePath;
                    if (!file_exists($fullPath)) {
                        $allFilesExist = false;
                        break;
                    }
                }

                if ($allFilesExist) {
                    $task['status'] = 'completed';
                    $completed[] = $taskId;
                }
            }
        }

        return $completed;
    }

    /**
     * Recalculate completion metrics
     */
    public function recalculateMetrics(): void
    {
        if (!isset($this->versionProgress['tasks'])) {
            return;
        }

        $total = count($this->versionProgress['tasks']);
        $completed = 0;
        $inProgress = 0;
        $pending = 0;

        foreach ($this->versionProgress['tasks'] as $task) {
            match ($task['status']) {
                'completed' => $completed++,
                'in_progress' => $inProgress++,
                'pending' => $pending++,
                default => null
            };
        }

        $this->versionProgress['summary']['total_planned_tasks'] = $total;
        $this->versionProgress['summary']['completed'] = $completed;
        $this->versionProgress['summary']['in_progress'] = $inProgress;
        $this->versionProgress['summary']['pending'] = $pending;
        $this->versionProgress['summary']['completion_percentage'] = $total > 0
            ? (int)(($completed / $total) * 100)
            : 0;
    }

    /**
     * Save version-specific progress
     */
    public function saveVersionProgress(): void
    {
        $file = $this->progressDir . '/progress-beta-' . $this->version . '.json';
        $this->versionProgress['last_updated'] = date('Y-m-d\TH:i:s\Z');
        file_put_contents($file, json_encode($this->versionProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Update master progress with current version status
     */
    public function updateMasterProgress(): void
    {
        if (!isset($this->masterProgress['versions'])) {
            $this->masterProgress['versions'] = [];
        }

        // Find and update existing version or create new
        $found = false;
        foreach ($this->masterProgress['versions'] as &$version) {
            if ($version['version'] === 'beta-' . $this->version) {
                $version['completion'] = $this->versionProgress['summary']['completion_percentage'] ?? 0;
                $version['status'] = $this->getVersionStatus();
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->masterProgress['versions'][] = [
                'version' => 'beta-' . $this->version,
                'file' => 'progress-beta-' . $this->version . '.json',
                'status' => $this->getVersionStatus(),
                'completion' => $this->versionProgress['summary']['completion_percentage'] ?? 0,
                'start_date' => $this->versionProgress['start_date'] ?? date('Y-m-d'),
            ];
        }

        $this->masterProgress['current_version'] = 'beta-' . $this->version;
        $this->masterProgress['last_updated'] = date('Y-m-d\TH:i:s\Z');
    }

    /**
     * Get version status based on completion percentage
     */
    private function getVersionStatus(): string
    {
        $completion = $this->versionProgress['summary']['completion_percentage'] ?? 0;

        return match (true) {
            $completion === 0 => 'PENDING',
            $completion < 100 => 'IN_PROGRESS',
            default => 'IMPLEMENTATION_COMPLETE'
        };
    }

    /**
     * Save master progress
     */
    public function saveMasterProgress(): void
    {
        $file = $this->progressDir . '/progress.json';
        $this->masterProgress['last_updated'] = date('Y-m-d\TH:i:s\Z');
        file_put_contents($file, json_encode($this->masterProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get list of completed tasks
     */
    public function getCompletedTasks(): array
    {
        $completed = [];
        if (!isset($this->versionProgress['tasks'])) {
            return $completed;
        }

        foreach ($this->versionProgress['tasks'] as $task) {
            if ($task['status'] === 'completed') {
                $completed[] = [
                    'id' => $task['id'],
                    'description' => $task['description'] ?? 'Unknown task'
                ];
            }
        }

        return $completed;
    }

    /**
     * Get list of in-progress tasks
     */
    public function getInProgressTasks(): array
    {
        $inProgress = [];
        if (!isset($this->versionProgress['tasks'])) {
            return $inProgress;
        }

        foreach ($this->versionProgress['tasks'] as $task) {
            if ($task['status'] === 'in_progress') {
                $inProgress[] = [
                    'id' => $task['id'],
                    'description' => $task['description'] ?? 'Unknown task'
                ];
            }
        }

        return $inProgress;
    }
}
