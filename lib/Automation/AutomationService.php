<?php
namespace NGN\Lib\Automation;

/**
 * AutomationService - Core orchestrator for automation workflows
 *
 * Coordinates progress tracking, documentation, git operations, and deployments
 */
class AutomationService
{
    private string $projectRoot;
    private string $version;
    private bool $dryRun = false;
    private array $config = [];
    private ProgressTracker $progressTracker;
    private ReadmeGenerator $readmeGenerator;
    private GitCommitter $gitCommitter;
    private FleetDeployer $fleetDeployer;
    private array $log = [];

    public function __construct(string $projectRoot, string $version = '', array $config = [])
    {
        $this->projectRoot = $projectRoot;
        $this->version = $version;
        $this->config = $config;

        // Initialize services
        $this->progressTracker = new ProgressTracker($projectRoot, $version);
        $this->gitCommitter = new GitCommitter($projectRoot, $version);
        $this->fleetDeployer = new FleetDeployer($projectRoot, $version);
    }

    /**
     * Set dry-run mode
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
        $this->progressTracker = new ProgressTracker($this->projectRoot, $this->version);
        $this->gitCommitter->setDryRun($dryRun);
        $this->fleetDeployer->setDryRun($dryRun);
    }

    /**
     * Execute full automation workflow
     */
    public function executeFull(): bool
    {
        $this->log('Starting full automation workflow');

        // 1. Validate state
        if (!$this->validateState()) {
            $this->log('State validation failed', 'error');
            return false;
        }
        $this->log('✓ State validated');

        // 2. Update progress
        if (!$this->updateProgress()) {
            $this->log('Progress update failed', 'error');
            return false;
        }
        $this->log('✓ Progress updated');

        // 3. Regenerate README
        if (!$this->regenerateReadme()) {
            $this->log('README regeneration failed', 'error');
            return false;
        }
        $this->log('✓ README regenerated');

        // 4. Create git commit
        if (!$this->createCommit()) {
            $this->log('Git commit failed', 'error');
            return false;
        }
        $this->log('✓ Git commit created');

        // 5. Push to remote
        if (!$this->pushToRemote()) {
            $this->log('Git push failed', 'error');
            return false;
        }
        $this->log('✓ Pushed to remote');

        // 6. Deploy via fleet
        if (!$this->deployViaFleet()) {
            $this->log('Fleet deployment failed', 'error');
            return false;
        }
        $this->log('✓ Fleet deployment successful');

        $this->log('Full workflow completed successfully');
        return true;
    }

    /**
     * Execute progress update only
     */
    public function executeProgress(): bool
    {
        $this->log('Updating progress for version ' . $this->version);

        if (!$this->validateState()) {
            return false;
        }

        return $this->updateProgress();
    }

    /**
     * Execute README update only
     */
    public function executeReadme(): bool
    {
        $this->log('Regenerating README');

        if (!$this->validateState()) {
            return false;
        }

        return $this->regenerateReadme();
    }

    /**
     * Execute commit only
     */
    public function executeCommit(): bool
    {
        $this->log('Creating git commit');

        if (!$this->validateState()) {
            return false;
        }

        return $this->createCommit() && $this->pushToRemote();
    }

    /**
     * Execute deployment only
     */
    public function executeDeploy(): bool
    {
        $this->log('Deploying via Fleet');

        $issues = $this->fleetDeployer->validatePrerequisites();
        if (!empty($issues)) {
            foreach ($issues as $issue) {
                $this->log('⚠ ' . $issue, 'warning');
            }
        }

        return $this->deployViaFleet();
    }

    /**
     * Validate system state
     */
    private function validateState(): bool
    {
        // Check git status
        if ($this->gitCommitter->hasUncommittedChanges() && !$this->dryRun) {
            $this->log('Warning: Uncommitted changes in git', 'warning');
        }

        // Check project root
        if (!is_dir($this->projectRoot)) {
            $this->log('Project root not found: ' . $this->projectRoot, 'error');
            return false;
        }

        // Check .env file
        if (!file_exists($this->projectRoot . '/.env')) {
            $this->log('.env file not found', 'warning');
        }

        return true;
    }

    /**
     * Update progress tracking
     */
    private function updateProgress(): bool
    {
        try {
            $this->progressTracker->loadMasterProgress();
            $this->progressTracker->loadVersionProgress($this->version);

            // Auto-detect completions based on file existence
            $fileChecks = $this->getFileChecksForVersion();
            $newCompletions = $this->progressTracker->autoDetectCompletions($fileChecks);

            if (!empty($newCompletions)) {
                $this->log('Auto-detected ' . count($newCompletions) . ' completed tasks');
            }

            // Recalculate metrics
            $this->progressTracker->recalculateMetrics();

            // Save progress
            if (!$this->dryRun) {
                $this->progressTracker->saveVersionProgress();
                $this->progressTracker->updateMasterProgress();
                $this->progressTracker->saveMasterProgress();
                $this->log('Progress files saved');
            } else {
                $this->log('(DRY-RUN) Would update progress files');
            }

            return true;
        } catch (\Throwable $e) {
            $this->log('Progress update failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Regenerate README with updated status
     */
    private function regenerateReadme(): bool
    {
        try {
            $versionProgress = $this->progressTracker->getVersionProgress();

            if (empty($versionProgress)) {
                $this->log('No version progress data available', 'warning');
                return false;
            }

            $this->readmeGenerator = new ReadmeGenerator(
                $this->projectRoot,
                $this->version,
                $versionProgress
            );

            if ($this->dryRun) {
                $this->log('(DRY-RUN) Would update README.md');
            } else {
                if (!$this->readmeGenerator->updateReadme()) {
                    $this->log('Failed to update README.md', 'error');
                    return false;
                }
                $this->log('README.md updated');
            }

            return true;
        } catch (\Throwable $e) {
            $this->log('README regeneration failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create and stage commit
     */
    private function createCommit(): bool
    {
        try {
            // Determine files to stage
            $filesToStage = [
                'storage/plan/progress.json',
                'storage/plan/progress-beta-' . $this->version . '.json',
                'README.md'
            ];

            // Stage files
            $this->gitCommitter->stageFiles($filesToStage);

            if (empty($this->gitCommitter->getStagedFiles())) {
                $this->log('No files to stage', 'warning');
                return false;
            }

            $this->log('Staged ' . count($this->gitCommitter->getStagedFiles()) . ' files');

            // Get completed tasks for commit message
            $completedTasks = $this->progressTracker->getCompletedTasks();
            $this->gitCommitter->setCompletedTasks($completedTasks);

            if ($this->dryRun) {
                $this->log('(DRY-RUN) Would create commit with message:');
                $this->log($this->gitCommitter->generateCommitMessage());
            } else {
                if (!$this->gitCommitter->commit()) {
                    $this->log('Failed to create commit', 'error');
                    return false;
                }
                $this->log('Commit created successfully');
            }

            return true;
        } catch (\Throwable $e) {
            $this->log('Commit creation failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Push to remote
     */
    private function pushToRemote(): bool
    {
        try {
            if ($this->dryRun) {
                $this->log('(DRY-RUN) Would push to origin/main');
                return true;
            }

            if (!$this->gitCommitter->push()) {
                $this->log('Failed to push to remote', 'error');
                return false;
            }

            $this->log('Pushed to origin/main');
            return true;
        } catch (\Throwable $e) {
            $this->log('Push failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Deploy via Fleet
     */
    private function deployViaFleet(): bool
    {
        try {
            $issues = $this->fleetDeployer->validatePrerequisites();

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $this->log('⚠ ' . $issue, 'warning');
                }

                if (count($issues) > 0 && !$this->dryRun) {
                    $this->log('Deployment prerequisites not met', 'error');
                    return false;
                }
            }

            if ($this->dryRun) {
                $this->log('(DRY-RUN) Would deploy via nexus fleet-deploy');
                return true;
            }

            if (!$this->fleetDeployer->deploy()) {
                $this->log('Fleet deployment failed', 'error');
                return false;
            }

            $this->log('Fleet deployment successful');
            return true;
        } catch (\Throwable $e) {
            $this->log('Deployment failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get file checks configuration for auto-detection
     */
    private function getFileChecksForVersion(): array
    {
        // This maps task IDs to file paths that indicate completion
        return [
            'blockchain_anchoring' => [
                'lib/Blockchain/BlockchainService.php',
                'lib/Blockchain/SmartContractInterface.php'
            ],
            'nft_certificate_minting' => [
                'lib/NFT/NFTService.php',
                'lib/NFT/ERC721Interface.php'
            ],
            'admin_ledger_dashboard' => [
                'public/admin-v2/src/pages/Ledger.tsx',
                'public/admin-v2/src/pages/LedgerDashboard.tsx'
            ]
        ];
    }

    /**
     * Get execution log
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Print execution log
     */
    public function printLog(): void
    {
        foreach ($this->log as $entry) {
            echo $this->formatLogEntry($entry) . "\n";
        }
    }

    /**
     * Format log entry with colors
     */
    private function formatLogEntry(array $entry): string
    {
        $level = $entry['level'];
        $message = $entry['message'];

        return match ($level) {
            'error' => "\033[91m✗ {$message}\033[0m",    // Red
            'warning' => "\033[93m⚠ {$message}\033[0m",  // Yellow
            'info' => "\033[92m✓ {$message}\033[0m",     // Green
            'debug' => "\033[94mℹ {$message}\033[0m",    // Blue
            default => $message
        };
    }

    /**
     * Log message
     */
    private function log(string $message, string $level = 'info'): void
    {
        $this->log[] = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message
        ];
    }

    /**
     * Get status summary
     */
    public function getStatus(): array
    {
        $this->progressTracker->loadVersionProgress($this->version);
        $versionProgress = $this->progressTracker->getVersionProgress();

        return [
            'version' => $this->version,
            'completion' => $versionProgress['summary']['completion_percentage'] ?? 0,
            'completed_tasks' => $versionProgress['summary']['completed'] ?? 0,
            'total_tasks' => $versionProgress['summary']['total_planned_tasks'] ?? 0,
            'current_branch' => $this->gitCommitter->getCurrentBranch(),
            'current_commit' => $this->gitCommitter->getCurrentCommitHash(),
            'has_uncommitted_changes' => $this->gitCommitter->hasUncommittedChanges(),
            'last_deployment' => $this->fleetDeployer->getLastDeployment()
        ];
    }
}
