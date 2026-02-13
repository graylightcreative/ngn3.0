<?php
namespace NGN\Lib\Automation;

/**
 * GitCommitter - Handles git operations
 *
 * Stages files, creates structured commits, and manages pushes
 */
class GitCommitter
{
    private string $projectRoot;
    private string $version;
    private array $completedTasks = [];
    private array $stagedFiles = [];
    private bool $dryRun = false;

    public function __construct(string $projectRoot, string $version = '')
    {
        $this->projectRoot = $projectRoot;
        $this->version = $version;
    }

    /**
     * Set dry-run mode
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Set completed tasks for commit message
     */
    public function setCompletedTasks(array $tasks): void
    {
        $this->completedTasks = $tasks;
    }

    /**
     * Check git status and return untracked/modified files
     */
    public function getGitStatus(): array
    {
        $status = shell_exec("cd {$this->projectRoot} && git status --porcelain 2>&1");
        $lines = array_filter(explode("\n", trim($status)));

        $result = [
            'untracked' => [],
            'modified' => [],
            'staged' => [],
            'deleted' => []
        ];

        foreach ($lines as $line) {
            $prefix = substr($line, 0, 2);
            $file = trim(substr($line, 3));

            match ($prefix) {
                '??' => $result['untracked'][] = $file,
                'M ' => $result['modified'][] = $file,
                'MM' => $result['staged'][] = $file,
                'D ' => $result['deleted'][] = $file,
                'AM' => $result['staged'][] = $file,
                'A ' => $result['staged'][] = $file,
                default => null
            };
        }

        return $result;
    }

    /**
     * Stage relevant files for commit
     */
    public function stageFiles(array $filePaths): bool
    {
        if (empty($filePaths)) {
            return true;
        }

        $this->stagedFiles = [];

        foreach ($filePaths as $file) {
            $fullPath = $this->projectRoot . '/' . $file;

            // Skip if file doesn't exist
            if (!file_exists($fullPath)) {
                continue;
            }

            if ($this->dryRun) {
                $this->stagedFiles[] = $file;
            } else {
                $cmd = "cd {$this->projectRoot} && git add " . escapeshellarg($file);
                shell_exec($cmd);
                $this->stagedFiles[] = $file;
            }
        }

        return true;
    }

    /**
     * Get files that will be staged
     */
    public function getStagedFiles(): array
    {
        return $this->stagedFiles;
    }

    /**
     * Generate structured commit message
     */
    public function generateCommitMessage(): string
    {
        $summary = "NGN {$this->version}: Progress update and deployment";

        if (!empty($this->completedTasks)) {
            $summary = "NGN {$this->version}: " . $this->getFirstTaskDescription();
        }

        $message = $summary . "\n\n";

        if (!empty($this->completedTasks)) {
            $message .= "Completed tasks:\n";
            foreach ($this->completedTasks as $task) {
                $message .= "- " . $task['description'] . "\n";
            }
            $message .= "\n";
        }

        $message .= "Files changed: " . count($this->stagedFiles) . "\n";
        $message .= "Completion: " . ($this->version ? "NGN {$this->version}" : "current") . "\n";

        return $message;
    }

    /**
     * Get first task description for commit summary
     */
    private function getFirstTaskDescription(): string
    {
        if (empty($this->completedTasks)) {
            return "Automated progress update";
        }

        return $this->completedTasks[0]['description'] ?? 'Progress update';
    }

    /**
     * Create commit with generated message
     */
    public function commit(): bool
    {
        if (empty($this->stagedFiles)) {
            return false;
        }

        $message = $this->generateCommitMessage();

        if ($this->dryRun) {
            return true;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'git_msg_');
        file_put_contents($tempFile, $message);

        $cmd = "cd {$this->projectRoot} && git commit -F " . escapeshellarg($tempFile);
        $output = shell_exec($cmd . " 2>&1");
        unlink($tempFile);

        // Check if commit was successful
        return strpos($output, 'create mode') !== false || strpos($output, 'changed') !== false;
    }

    /**
     * Push to remote
     */
    public function push(string $remote = 'origin', string $branch = 'main'): bool
    {
        if ($this->dryRun) {
            return true;
        }

        $cmd = "cd {$this->projectRoot} && git push {$remote} {$branch} 2>&1";
        $output = shell_exec($cmd);

        return strpos($output, 'error') === false && strpos($output, 'fatal') === false;
    }

    /**
     * Get current commit hash
     */
    public function getCurrentCommitHash(): string
    {
        $hash = trim(shell_exec("cd {$this->projectRoot} && git rev-parse HEAD 2>&1"));
        return strlen($hash) === 40 ? $hash : '';
    }

    /**
     * Get current branch
     */
    public function getCurrentBranch(): string
    {
        return trim(shell_exec("cd {$this->projectRoot} && git rev-parse --abbrev-ref HEAD 2>&1"));
    }

    /**
     * Check if there are uncommitted changes
     */
    public function hasUncommittedChanges(): bool
    {
        $status = shell_exec("cd {$this->projectRoot} && git status --porcelain 2>&1");
        return !empty(trim($status));
    }
}
