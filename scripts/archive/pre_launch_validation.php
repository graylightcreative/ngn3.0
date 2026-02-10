<?php
/**
 * Pre-Launch Validation Script
 * DAY 4-5: Run this before going live to verify everything is ready
 * Usage: php scripts/pre_launch_validation.php
 */

class PreLaunchValidator {
    private $results = [];
    private $passed = 0;
    private $warnings = 0;
    private $failed = 0;

    public function validate() {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════╗\n";
        echo "║     NGN 2.0.1 PRE-LAUNCH VALIDATION                    ║\n";
        echo "║     Run this before going live to ensure readiness     ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n";
        echo "\n";

        $this->checkEnvironment();
        $this->checkDatabase();
        $this->checkFiles();
        $this->checkConfiguration();
        $this->checkSecurity();
        $this->checkServices();

        $this->printSummary();
    }

    private function checkEnvironment() {
        echo "1. ENVIRONMENT CHECKS\n";
        echo str_repeat("─", 50) . "\n";

        // PHP version
        $php_version = phpversion();
        if (version_compare($php_version, '8.1', '>=')) {
            $this->pass("PHP version: $php_version ✅");
        } else {
            $this->fail("PHP version: $php_version (need >= 8.1)");
        }

        // Extensions
        $required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->pass("PHP extension: $ext ✅");
            } else {
                $this->fail("PHP extension: $ext ❌");
            }
        }

        // .env file
        if (file_exists(__DIR__ . '/../.env')) {
            $this->pass(".env file exists ✅");
        } else {
            $this->fail(".env file missing ❌");
        }

        echo "\n";
    }

    private function checkDatabase() {
        echo "2. DATABASE CHECKS\n";
        echo str_repeat("─", 50) . "\n";

        // Check tables exist
        $tables = [
            'directorate_sirs',
            'sir_feedback',
            'sir_audit_log',
            'sir_notifications',
            'user_subscriptions'
        ];

        try {
            // Would need actual DB connection, but we can verify file structure
            $migration_file = __DIR__ . '/../migrations/sql/schema/45_directorate_sir_registry.sql';
            if (file_exists($migration_file)) {
                $content = file_get_contents($migration_file);
                foreach ($tables as $table) {
                    if (strpos($content, "CREATE TABLE $table") !== false) {
                        $this->pass("Migration for table '$table' found ✅");
                    } else {
                        $this->warn("Migration for table '$table' may be missing ⚠️");
                    }
                }
            } else {
                $this->fail("Migration file not found ❌");
            }
        } catch (Exception $e) {
            $this->warn("Database check: " . $e->getMessage());
        }

        echo "\n";
    }

    private function checkFiles() {
        echo "3. FILE STRUCTURE CHECKS\n";
        echo str_repeat("─", 50) . "\n";

        $critical_files = [
            'lib/Governance/DirectorateRoles.php',
            'lib/Governance/SirRegistryService.php',
            'lib/Governance/SirAuditService.php',
            'lib/Governance/SirNotificationService.php',
            'public/api/v1/governance/sir.php',
            'public/api/v1/governance/sir_detail.php',
            'public/api/v1/governance/sir_verify.php',
            'public/api/v1/governance/sir_feedback.php',
            'public/api/v1/governance/dashboard.php',
            'public/webhooks/stripe.php',
            'jobs/governance/send_sir_reminders.php',
            'jobs/governance/generate_governance_report.php',
            'tests/Governance/DirectorateRolesTest.php',
            'tests/Governance/SirAuditServiceTest.php',
            'tests/Governance/SirWorkflowTest.php',
        ];

        $root = __DIR__ . '/..';

        foreach ($critical_files as $file) {
            $path = "$root/$file";
            if (file_exists($path)) {
                $this->pass("File exists: $file ✅");

                // Check PHP syntax
                $output = [];
                $return_var = 0;
                exec("php -l '$path' 2>&1", $output, $return_var);

                if ($return_var === 0) {
                    $this->pass("PHP syntax valid: $file ✅");
                } else {
                    $this->fail("PHP syntax error in $file ❌");
                }
            } else {
                $this->fail("File missing: $file ❌");
            }
        }

        echo "\n";
    }

    private function checkConfiguration() {
        echo "4. CONFIGURATION CHECKS\n";
        echo str_repeat("─", 50) . "\n";

        // Check .env has required keys
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);

            $required_vars = [
                'GOVERNANCE_CHAIRMAN_USER_ID',
                'GOVERNANCE_BRANDON_USER_ID',
                'GOVERNANCE_PEPPER_USER_ID',
                'GOVERNANCE_ERIK_USER_ID',
                'STRIPE_WEBHOOK_SECRET',
                'STRIPE_API_KEY'
            ];

            foreach ($required_vars as $var) {
                if (strpos($env_content, $var) !== false) {
                    $this->pass(".env has $var ✅");
                } else {
                    $this->warn(".env missing $var (may need to configure) ⚠️");
                }
            }

            // Check DEBUG is off for production
            if (strpos($env_content, 'DEBUG=false') !== false || strpos($env_content, 'APP_ENV=production') !== false) {
                $this->pass("Production configuration detected ✅");
            } else {
                $this->warn("Verify DEBUG is disabled in production ⚠️");
            }
        }

        // Check storage/logs directory writable
        $logs_dir = __DIR__ . '/../storage/logs';
        if (is_dir($logs_dir)) {
            if (is_writable($logs_dir)) {
                $this->pass("Storage/logs directory writable ✅");
            } else {
                $this->warn("Storage/logs directory not writable ⚠️");
            }
        } else {
            $this->warn("Storage/logs directory doesn't exist ⚠️");
        }

        echo "\n";
    }

    private function checkSecurity() {
        echo "5. SECURITY CHECKS\n";
        echo str_repeat("─", 50) . "\n";

        // Check for common vulnerabilities
        $files_to_check = [
            __DIR__ . '/../public/webhooks/stripe.php',
            __DIR__ . '/../public/api/v1/governance/sir.php'
        ];

        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);

                // Check for prepared statements
                if (strpos($content, '$pdo->prepare') !== false || strpos($content, 'prepare(') !== false) {
                    $this->pass("Prepared statements found: " . basename($file) . " ✅");
                } else {
                    $this->warn("Verify prepared statements in " . basename($file) . " ⚠️");
                }

                // Check for exception handling
                if (strpos($content, 'try') !== false && strpos($content, 'catch') !== false) {
                    $this->pass("Exception handling found: " . basename($file) . " ✅");
                } else {
                    $this->warn("Limited exception handling in " . basename($file) . " ⚠️");
                }
            }
        }

        echo "\n";
    }

    private function checkServices() {
        echo "6. EXTERNAL SERVICES CHECK\n";
        echo str_repeat("─", 50) . "\n";

        // Stripe configuration
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);

            // Check for test vs live keys
            if (preg_match('/STRIPE_API_KEY\s*=\s*sk_test_/', $env_content)) {
                $this->warn("Stripe TEST key detected (fine for staging) ⚠️");
            } elseif (preg_match('/STRIPE_API_KEY\s*=\s*sk_live_/', $env_content)) {
                $this->pass("Stripe LIVE key configured (production ready) ✅");
            } else {
                $this->warn("Stripe API key not configured or invalid ⚠️");
            }
        }

        echo "\n";
    }

    private function pass($message) {
        echo "  ✅ $message\n";
        $this->passed++;
    }

    private function warn($message) {
        echo "  ⚠️  $message\n";
        $this->warnings++;
    }

    private function fail($message) {
        echo "  ❌ $message\n";
        $this->failed++;
    }

    private function printSummary() {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════╗\n";
        echo "║                    VALIDATION RESULTS                  ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n";
        echo "\n";

        echo "✅ Passed:  $this->passed\n";
        echo "⚠️  Warnings: $this->warnings\n";
        echo "❌ Failed:  $this->failed\n";
        echo "\n";

        if ($this->failed === 0) {
            echo "╔════════════════════════════════════════════════════════╗\n";
            echo "║  ✅ VALIDATION PASSED - READY FOR LAUNCH              ║\n";
            echo "╚════════════════════════════════════════════════════════╝\n";
            echo "\n";

            echo "Next steps:\n";
            echo "1. Address any warnings above\n";
            echo "2. Run: php vendor/bin/phpunit tests/Governance/\n";
            echo "3. Execute: bash scripts/setup_cron_jobs.sh\n";
            echo "4. Follow: DAY5_LAUNCH_RUNBOOK.md\n";
            echo "\n";

            exit(0);
        } else {
            echo "╔════════════════════════════════════════════════════════╗\n";
            echo "║  ❌ VALIDATION FAILED - FIX ISSUES BEFORE LAUNCH       ║\n";
            echo "╚════════════════════════════════════════════════════════╝\n";
            echo "\n";

            echo "Failed items to fix:\n";
            echo "1. Review errors above\n";
            echo "2. Make corrections\n";
            echo "3. Re-run this script\n";
            echo "4. Do not proceed until all ❌ are fixed\n";
            echo "\n";

            exit(1);
        }
    }
}

// Run validation
$validator = new PreLaunchValidator();
$validator->validate();
