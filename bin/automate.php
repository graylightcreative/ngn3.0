#!/usr/bin/env php
<?php
/**
 * NGN Automation Master Script
 *
 * Orchestrates progress tracking, git commits, and deployments in a single command.
 * Usage: php bin/automate.php [command] [options]
 */

// Load project root and bootstrap
$root = dirname(dirname(__FILE__));
require_once $root . '/lib/bootstrap.php';

use NGN\Lib\Automation\AutomationService;

// ============================================================================
// CLI Colors and Formatting
// ============================================================================

class CliFormatter
{
    public static function header(string $text): string
    {
        return "\n\033[1;36m" . str_repeat("=", 80) . "\033[0m\n"
            . "\033[1;36m" . $text . "\033[0m\n"
            . "\033[1;36m" . str_repeat("=", 80) . "\033[0m\n";
    }

    public static function success(string $text): string
    {
        return "\033[92m✓ {$text}\033[0m";
    }

    public static function error(string $text): string
    {
        return "\033[91m✗ {$text}\033[0m";
    }

    public static function warning(string $text): string
    {
        return "\033[93m⚠ {$text}\033[0m";
    }

    public static function info(string $text): string
    {
        return "\033[94mℹ {$text}\033[0m";
    }

    public static function section(string $text): string
    {
        return "\n\033[1;36m{$text}\033[0m";
    }
}

// ============================================================================
// Parse Command Line Arguments
// ============================================================================

$command = isset($argv[1]) ? $argv[1] : 'help';
$options = [];

// Parse remaining arguments
for ($i = 2; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $key = $parts[0];
        $value = isset($parts[1]) ? $parts[1] : true;
        $options[$key] = $value;
    }
}

// ============================================================================
// Get Version from Options or Environment
// ============================================================================

$version = $options['version'] ?? getenv('APP_VERSION') ?? '2.1.0';
$dryRun = isset($options['dry-run']) || isset($options['dryrun']);

// ============================================================================
// Initialize Automation Service
// ============================================================================

$service = new AutomationService($root, $version);

if ($dryRun) {
    $service->setDryRun(true);
    echo CliFormatter::warning("DRY-RUN MODE - No changes will be made\n");
}

// ============================================================================
// Command Handlers
// ============================================================================

echo CliFormatter::header("NGN Automation Master Script v1.0");

switch ($command) {
    case 'full':
        handleFullWorkflow($service, $version, $dryRun);
        break;

    case 'progress':
        handleProgressCommand($service);
        break;

    case 'readme':
        handleReadmeCommand($service);
        break;

    case 'commit':
        handleCommitCommand($service);
        break;

    case 'deploy':
        handleDeployCommand($service);
        break;

    case 'status':
        handleStatusCommand($service);
        break;

    case 'help':
        showHelp();
        break;

    case 'rollback':
        handleRollbackCommand($service, $options);
        break;

    default:
        echo CliFormatter::error("Unknown command: {$command}\n");
        showHelp();
        exit(1);
}

exit(0);

// ============================================================================
// Command Implementations
// ============================================================================

function handleFullWorkflow(AutomationService $service, string $version, bool $dryRun): void
{
    echo CliFormatter::section("Full Automation Workflow");
    echo "Version: {$version}\n";

    if ($dryRun) {
        echo CliFormatter::warning("DRY-RUN: No changes will be committed or deployed\n");
    }

    echo "\nProgress: ";

    if ($service->executeFull()) {
        echo "\n";
        foreach ($service->getLog() as $entry) {
            if ($entry['level'] === 'error') {
                echo CliFormatter::error($entry['message']) . "\n";
            } elseif ($entry['level'] === 'warning') {
                echo CliFormatter::warning($entry['message']) . "\n";
            } else {
                echo CliFormatter::success($entry['message']) . "\n";
            }
        }

        if ($dryRun) {
            echo "\n" . CliFormatter::warning("DRY-RUN COMPLETE - No changes made\n");
        } else {
            echo "\n" . CliFormatter::success("WORKFLOW COMPLETE - All steps successful\n");
        }
    } else {
        echo "\n";
        foreach ($service->getLog() as $entry) {
            if ($entry['level'] === 'error') {
                echo CliFormatter::error($entry['message']) . "\n";
            } elseif ($entry['level'] === 'warning') {
                echo CliFormatter::warning($entry['message']) . "\n";
            } else {
                echo CliFormatter::success($entry['message']) . "\n";
            }
        }
        echo "\n" . CliFormatter::error("WORKFLOW FAILED\n");
        exit(1);
    }
}

function handleProgressCommand(AutomationService $service): void
{
    echo CliFormatter::section("Updating Progress");

    if ($service->executeProgress()) {
        echo CliFormatter::success("Progress updated successfully\n");
    } else {
        echo CliFormatter::error("Failed to update progress\n");
        exit(1);
    }
}

function handleReadmeCommand(AutomationService $service): void
{
    echo CliFormatter::section("Regenerating README");

    if ($service->executeReadme()) {
        echo CliFormatter::success("README regenerated successfully\n");
    } else {
        echo CliFormatter::error("Failed to regenerate README\n");
        exit(1);
    }
}

function handleCommitCommand(AutomationService $service): void
{
    echo CliFormatter::section("Creating Git Commit");

    if ($service->executeCommit()) {
        echo CliFormatter::success("Commit created and pushed successfully\n");
    } else {
        echo CliFormatter::error("Failed to create commit\n");
        exit(1);
    }
}

function handleDeployCommand(AutomationService $service): void
{
    echo CliFormatter::section("Deploying via Fleet");

    if ($service->executeDeploy()) {
        echo CliFormatter::success("Deployment successful\n");
    } else {
        echo CliFormatter::error("Deployment failed\n");
        exit(1);
    }
}

function handleStatusCommand(AutomationService $service): void
{
    echo CliFormatter::section("Current Status");

    $status = $service->getStatus();

    echo "Version: {$status['version']}\n";
    echo "Completion: {$status['completion']}% ({$status['completed_tasks']}/{$status['total_tasks']} tasks)\n";
    echo "Branch: {$status['current_branch']}\n";
    echo "Commit: {$status['current_commit']}\n";

    if ($status['has_uncommitted_changes']) {
        echo CliFormatter::warning("Uncommitted changes detected\n");
    } else {
        echo CliFormatter::success("Working directory clean\n");
    }

    if ($status['last_deployment']) {
        echo "\nLast Deployment:\n";
        echo "  Time: " . $status['last_deployment']['timestamp'] . "\n";
        echo "  Status: " . $status['last_deployment']['status'] . "\n";
    }
}

function handleRollbackCommand(AutomationService $service, array $options): void
{
    echo CliFormatter::section("Rolling Back");

    $commitHash = $options['to'] ?? null;

    if (!$commitHash) {
        echo CliFormatter::error("--to=<commit-hash> required\n");
        exit(1);
    }

    echo "Rolling back to: {$commitHash}\n";
    echo CliFormatter::warning("This operation cannot be undone. Continue? (yes/no): ");

    $input = trim(fgets(STDIN));
    if ($input !== 'yes') {
        echo "Rollback cancelled\n";
        exit(0);
    }

    // Execute rollback
    $root = dirname(dirname(__FILE__));
    $cmd = "cd {$root} && git reset --hard {$commitHash}";
    echo shell_exec($cmd);

    echo CliFormatter::success("Rollback complete\n");
}

function showHelp(): void
{
    $help = <<<EOH

NGN Automation Master Script - Orchestrate deployment workflows

USAGE:
  php bin/automate.php [command] [options]

COMMANDS:
  full          Execute complete workflow (progress → readme → commit → deploy)
  progress      Update progress tracking files
  readme        Regenerate README with status banners
  commit        Create git commit and push
  deploy        Deploy via Graylight Fleet
  status        Display current status
  rollback      Rollback to previous commit
  help          Show this help message

OPTIONS:
  --version=X.X.X    Target version (default: 2.1.0)
  --dry-run          Preview changes without executing
  --to=<hash>        Commit hash for rollback

EXAMPLES:
  # Full workflow (recommended)
  php bin/automate.php full --version=2.1.0

  # Test without making changes
  php bin/automate.php full --version=2.1.0 --dry-run

  # Individual operations
  php bin/automate.php progress
  php bin/automate.php readme
  php bin/automate.php commit

  # Rollback
  php bin/automate.php rollback --to=79d5e67

WORKFLOW:
  1. Validate system state (git, files, .env)
  2. Update progress tracking (auto-detect completions)
  3. Regenerate README with status banners
  4. Create structured git commit
  5. Push to origin/main
  6. Deploy via nexus fleet-deploy

SAFETY:
  • Use --dry-run to preview changes
  • Check git status before running
  • Ensure .env is properly configured
  • Verify credentials before deployment

For more information, see README.md

EOH;

    echo $help;
}
