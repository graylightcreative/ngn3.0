<?php
namespace NGN\Lib\Automation;

/**
 * ReadmeGenerator - Generates and updates README status sections
 *
 * Parses existing README.md and updates dynamic status banners
 * while preserving manual content sections
 */
class ReadmeGenerator
{
    private string $projectRoot;
    private string $version;
    private array $versionProgress;

    public function __construct(string $projectRoot, string $version, array $versionProgress)
    {
        $this->projectRoot = $projectRoot;
        $this->version = $version;
        $this->versionProgress = $versionProgress;
    }

    /**
     * Generate status banner for current version
     */
    public function generateStatusBanner(): string
    {
        $completion = $this->versionProgress['summary']['completion_percentage'] ?? 0;
        $completed = $this->versionProgress['summary']['completed'] ?? 0;
        $total = $this->versionProgress['summary']['total_planned_tasks'] ?? 0;

        $status = match (true) {
            $completion === 0 => '🚀 PLANNING',
            $completion < 25 => '🔧 EARLY_DEVELOPMENT',
            $completion < 50 => '⚙️ ACTIVE_DEVELOPMENT',
            $completion < 75 => '🧪 TESTING',
            $completion < 100 => '✅ READY_FOR_RELEASE',
            default => '✅ COMPLETE'
        };

        $progressBar = $this->generateProgressBar($completion);

        return <<<EOB
<!-- AUTO-GENERATED STATUS BANNER - v{$this->version} -->
## Status: {$status}

**Progress:** {$completed}/{$total} tasks completed ({$completion}%)

{$progressBar}

**Latest Update:** {$this->getCurrentDate()}

<!-- END AUTO-GENERATED BANNER -->

EOB;
    }

    /**
     * Generate visual progress bar
     */
    private function generateProgressBar(int $completion): string
    {
        $filled = (int)(($completion / 100) * 20);
        $empty = 20 - $filled;

        $bar = '█' . str_repeat('█', $filled) . str_repeat('░', $empty) . '█';

        return "```\n{$bar} {$completion}%\n```";
    }

    /**
     * Generate detailed task summary
     */
    public function generateTaskSummary(): string
    {
        $summary = "### Task Summary\n\n";

        $completed = array_filter(
            $this->versionProgress['tasks'] ?? [],
            fn($task) => $task['status'] === 'completed'
        );

        if (!empty($completed)) {
            $summary .= "#### ✅ Completed\n\n";
            foreach ($completed as $task) {
                $summary .= "- " . $task['description'] . " (`{$task['id']}`)\n";
            }
            $summary .= "\n";
        }

        $inProgress = array_filter(
            $this->versionProgress['tasks'] ?? [],
            fn($task) => $task['status'] === 'in_progress'
        );

        if (!empty($inProgress)) {
            $summary .= "#### 🔄 In Progress\n\n";
            foreach ($inProgress as $task) {
                $summary .= "- " . $task['description'] . " (`{$task['id']}`)\n";
            }
            $summary .= "\n";
        }

        $pending = array_filter(
            $this->versionProgress['tasks'] ?? [],
            fn($task) => $task['status'] === 'pending'
        );

        if (!empty($pending)) {
            $summary .= "#### ⏳ Pending\n\n";
            foreach ($pending as $task) {
                $summary .= "- " . $task['description'] . " (`{$task['id']}`)\n";
            }
            $summary .= "\n";
        }

        return $summary;
    }

    /**
     * Update README with new status
     * Preserves manual content by detecting section markers
     */
    public function updateReadme(): bool
    {
        $readmePath = $this->projectRoot . '/README.md';

        if (!file_exists($readmePath)) {
            return false;
        }

        $content = file_get_contents($readmePath);

        // Find and replace auto-generated banner
        $newBanner = $this->generateStatusBanner();
        $pattern = '/<!-- AUTO-GENERATED STATUS BANNER.*?<!-- END AUTO-GENERATED BANNER -->/s';

        if (preg_match($pattern, $content)) {
            // Replace existing banner
            $content = preg_replace($pattern, $newBanner, $content);
        } else {
            // Insert after first heading if no banner exists
            $content = preg_replace(
                '/^(# .+\n)/m',
                "$1\n" . $newBanner . "\n",
                $content,
                1
            );
        }

        return file_put_contents($readmePath, $content) !== false;
    }

    /**
     * Generate deployment notes section
     */
    public function generateDeploymentNotes(): string
    {
        $notes = "## Deployment Notes - v{$this->version}\n\n";

        $notes .= "### Prerequisites\n";
        $notes .= "- [ ] All tests passing\n";
        $notes .= "- [ ] Database migrations applied\n";
        $notes .= "- [ ] Environment variables configured\n";
        $notes .= "- [ ] Backup taken\n\n";

        $notes .= "### Deployment Steps\n";
        $notes .= "1. `git pull origin main`\n";
        $notes .= "2. `php bin/automate.php progress`\n";
        $notes .= "3. `php bin/automate.php deploy`\n\n";

        $notes .= "### Rollback Plan\n";
        $notes .= "If issues occur, use: `php bin/automate.php rollback --to=<commit-hash>`\n";

        return $notes;
    }

    /**
     * Get current date in ISO format
     */
    private function getCurrentDate(): string
    {
        return date('Y-m-d\TH:i:s\Z');
    }
}
