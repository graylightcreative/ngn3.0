<?php
namespace NGN\Lib\Automation;

/**
 * FleetDeployer - Handles Graylight Fleet deployments
 *
 * Wraps nexus fleet-deploy command and manages deployment validation
 */
class FleetDeployer
{
    private string $projectRoot;
    private string $version;
    private string $environment = 'beta';
    private bool $dryRun = false;
    private array $deploymentHistory = [];

    public function __construct(string $projectRoot, string $version = '')
    {
        $this->projectRoot = $projectRoot;
        $this->version = $version;
        $this->loadDeploymentHistory();
    }

    /**
     * Set dry-run mode
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Set target environment
     */
    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Check if nexus command is available
     */
    public function isNexusAvailable(): bool
    {
        $result = shell_exec('which nexus 2>&1');
        return !empty($result);
    }

    /**
     * Load deployment history
     */
    private function loadDeploymentHistory(): void
    {
        $historyFile = $this->projectRoot . '/storage/logs/automation/deploy-history.json';

        if (file_exists($historyFile)) {
            $this->deploymentHistory = json_decode(file_get_contents($historyFile), true) ?? [];
        }

        if (!isset($this->deploymentHistory['deployments'])) {
            $this->deploymentHistory['deployments'] = [];
        }
    }

    /**
     * Execute deployment
     */
    public function deploy(): bool
    {
        if (!$this->isNexusAvailable()) {
            return false;
        }

        if ($this->dryRun) {
            return true;
        }

        $cmd = "nexus fleet-deploy --environment={$this->environment} 2>&1";
        $output = shell_exec($cmd);

        $success = strpos($output, 'error') === false && strpos($output, 'failed') === false;

        if ($success) {
            $this->recordDeployment($output);
        }

        return $success;
    }

    /**
     * Record deployment in audit log
     */
    private function recordDeployment(string $output): void
    {
        $deployment = [
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
            'environment' => $this->environment,
            'status' => 'success',
            'output_summary' => substr($output, 0, 500),
            'deployed_by' => get_current_user() ?: 'automation'
        ];

        $this->deploymentHistory['deployments'][] = $deployment;
        $this->saveDeploymentHistory();
    }

    /**
     * Save deployment history
     */
    private function saveDeploymentHistory(): void
    {
        $historyDir = $this->projectRoot . '/storage/logs/automation';

        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0755, true);
        }

        $historyFile = $historyDir . '/deploy-history.json';
        file_put_contents($historyFile, json_encode($this->deploymentHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get last deployment record
     */
    public function getLastDeployment(): ?array
    {
        if (empty($this->deploymentHistory['deployments'])) {
            return null;
        }

        return end($this->deploymentHistory['deployments']);
    }

    /**
     * Get deployment history for version
     */
    public function getDeploymentHistoryForVersion(string $version): array
    {
        return array_filter(
            $this->deploymentHistory['deployments'] ?? [],
            fn($d) => $d['version'] === $version
        );
    }

    /**
     * Validate deployment prerequisites
     */
    public function validatePrerequisites(): array
    {
        $issues = [];

        if (!$this->isNexusAvailable()) {
            $issues[] = 'nexus command not found - install Graylight Fleet CLI';
        }

        // Check .env for SSH credentials
        $envFile = $this->projectRoot . '/.env';
        if (!file_exists($envFile)) {
            $issues[] = '.env file not found - cannot load deployment credentials';
        }

        // Check if git is clean
        $gitStatus = trim(shell_exec("cd {$this->projectRoot} && git status --porcelain 2>&1"));
        if (!empty($gitStatus)) {
            $issues[] = 'Uncommitted changes in git - commit before deploying';
        }

        return $issues;
    }

    /**
     * Generate deployment report
     */
    public function generateDeploymentReport(): string
    {
        $report = "## Deployment Report\n\n";
        $report .= "**Version:** {$this->version}\n";
        $report .= "**Environment:** {$this->environment}\n";
        $report .= "**Timestamp:** " . date('Y-m-d H:i:s') . "\n\n";

        $lastDeployment = $this->getLastDeployment();
        if ($lastDeployment) {
            $report .= "### Last Deployment\n";
            $report .= "- **Time:** " . $lastDeployment['timestamp'] . "\n";
            $report .= "- **Status:** " . $lastDeployment['status'] . "\n";
            $report .= "- **Deployed By:** " . $lastDeployment['deployed_by'] . "\n\n";
        }

        $history = $this->getDeploymentHistoryForVersion($this->version);
        if (!empty($history)) {
            $report .= "### Version Deployment History\n";
            $report .= "Total deployments: " . count($history) . "\n";
        }

        return $report;
    }
}
