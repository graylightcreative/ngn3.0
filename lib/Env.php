<?php
namespace NGN\Lib;

use Dotenv\Dotenv;

class Env
{
    public static function load(string $rootPath): void
    {
        // Normalize root
        $rootPath = rtrim($rootPath, '/');

        // Prepare diagnostics about search paths
        $legacyDir = $rootPath . '/lib/definitions';
        $pathsChecked = [
            $rootPath . '/.env',
            $legacyDir . '/.env',
        ];
        // Helper to export a diagnostic value to all env channels
        $export = function(string $key, string $value): void {
            if (function_exists('putenv')) { @\putenv($key . '=' . $value); }
            $_ENV[$key] = $value; $_SERVER[$key] = $value;
        };
        // Helper to check if a key is already set in any env channel
        $isSet = function(string $key): bool {
            $v = $_ENV[$key] ?? $_SERVER[$key] ?? \getenv($key);
            return !($v === false || $v === null || $v === '');
        };
        // Helper to mark where we loaded from for diagnostics
        $markLoadedFrom = function (string $path) use ($export, $pathsChecked) {
            $export('NGN_ENV_LOADED_FROM', $path);
            $export('NGN_ENV_SEARCH_PATHS', implode(',', $pathsChecked));
        };
        // Helper to mark when legacy values were imported (merged)
        $markImportedFrom = function (string $path) use ($export) {
            $export('NGN_ENV_IMPORTED_FROM', $path);
        };
        $markExampleOnly = function (string $examplePath) use ($export, $markLoadedFrom) {
            $export('NGN_ENV_EXAMPLE_ONLY', 'true');
            $markLoadedFrom($examplePath);
        };

        // Attempt to load environment using vlucas/phpdotenv if available.
        if (class_exists(\Dotenv\Dotenv::class)) {
            // Try root first for a real .env
            if (is_file($rootPath.'/.env')) {
                // Load root .env first
                $dotenv = Dotenv::createImmutable($rootPath);
                $dotenv->safeLoad();
                // Add logging after safeLoad to check APP_ENV
                $appEnvValue = Env::get('APP_ENV');
                file_put_contents(self::getLogFilePath('Env::load (phpdotenv) - APP_ENV: ' . ($appEnvValue === null ? 'null' : $appEnvValue) . "\n"), FILE_APPEND);
                $markLoadedFrom($rootPath.'/.env');
                // Merge legacy definitions for any keys not already set
                $legacyPath = $legacyDir.'/.env';
                if (is_file($legacyPath)) {
                    $lines = @file($legacyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                    $imported = false;
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || $line[0] === '#') continue;
                        if (stripos($line, 'export ') === 0) { $line = trim(substr($line, 7)); }
                        $parts = explode('=', $line, 2);
                        if (count($parts) !== 2) continue;
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        if ($key === '') continue;
                        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                            $val = substr($val, 1, -1);
                        }
                        if (!$isSet($key)) {
                            if (function_exists('putenv')) { @\putenv($key . '=' . $val); }
                            $_ENV[$key] = $val; $_SERVER[$key] = $val;
                            $imported = true;
                        }
                    }
                    if ($imported) { $markImportedFrom($legacyPath); }
                }
                return;
            }
            // Fallback to legacy location used by NGN 1.0 definitions (real .env only)
            if (is_file($legacyDir.'/.env')) {
                $dotenv = Dotenv::createImmutable($legacyDir);
                $dotenv->safeLoad();
                $appEnvValue = Env::get('APP_ENV');
                file_put_contents(self::getLogFilePath('Env::load (phpdotenv legacy) - APP_ENV: ' . ($appEnvValue === null ? 'null' : $appEnvValue) . "\n"), FILE_APPEND);
                $markLoadedFrom($legacyDir.'/.env');
                return;
            }
            // If only .env.example exists, do NOT load it — mark diagnostics so callers can warn.
            if (is_file($rootPath.'/.env.example')) {
                $markExampleOnly($rootPath.'/.env.example');
            } elseif (is_file($legacyDir.'/.env.example')) {
                $markExampleOnly($legacyDir.'/.env.example');
            } else {
                // No .env or example found; set explicit diagnostics
                $markLoadedFrom('none');
            }
            // nothing to load
            return;
        }

        // Fallback: lightweight .env loader when Dotenv is unavailable (e.g., Composer missing/stale)
        $envFile = null;
        $examplePath = null;
        if (is_file($rootPath.'/.env')) {
            $envFile = $rootPath.'/.env';
        } elseif (is_file($legacyDir.'/.env')) {
            $envFile = $legacyDir.'/.env';
        } else {
            // Track example if present for diagnostics, but do not load it
            if (is_file($rootPath.'/.env.example')) {
                $examplePath = $rootPath.'/.env.example';
            } elseif (is_file($legacyDir.'/.env.example')) {
                $examplePath = $legacyDir.'/.env.example';
            }
        }
        if ($envFile === null) {
            if ($examplePath) {
                $export('NGN_ENV_EXAMPLE_ONLY', 'true');
                $markLoadedFrom($examplePath);
            } else {
                $markLoadedFrom('none');
            }
            return;
        }
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) { $markLoadedFrom('none'); return; }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            // support export KEY=VAL syntax
            if (stripos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // strip surrounding quotes
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            // basic unescaping for common sequences
            $val = str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $val);
            // set env (avoid putenv fatal on hosts that disable it)
            if (function_exists('putenv')) { @\putenv($key . '=' . $val); }
            $_ENV[$key] = $val; $_SERVER[$key] = $val;
            // Add logging for APP_ENV in fallback loader
            if ($key === 'APP_ENV') {
                file_put_contents(self::getLogFilePath('Env::load (fallback) - APP_ENV: ' . $val . "\n"), FILE_APPEND);
            }
        }
        $markLoadedFrom($envFile);
        // If we loaded root .env via fallback, merge legacy values for missing keys
        if ($envFile === $rootPath.'/.env' && is_file($legacyDir.'/.env')) {
            $lines2 = @file($legacyDir.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $imported2 = false;
            foreach ($lines2 as $line2) {
                $line2 = trim($line2);
                if ($line2 === '' || $line2[0] === '#') continue;
                if (stripos($line2, 'export ') === 0) { $line2 = trim(substr($line2, 7)); }
                $parts2 = explode('=', $line2, 2);
                if (count($parts2) !== 2) continue;
                $k2 = trim($parts2[0]);
                $v2 = trim($parts2[1]);
                if ($k2 === '') continue;
                if ((str_starts_with($v2, '"') && str_ends_with($v2, '"')) || (str_starts_with($v2, "'") && str_ends_with($v2, "'"))) {
                    $v2 = substr($v2, 1, -1);
                }
                if (!isset($_ENV[$k2]) && !isset($_SERVER[$k2]) && (\getenv($k2) === false)) {
                    if (function_exists('putenv')) { @\putenv($k2 . '=' . $v2); }
                    $_ENV[$k2] = $v2; $_SERVER[$k2] = $v2;
                    $imported2 = true;
                }
            }
            if ($imported2) { $markImportedFrom($legacyDir.'/.env'); }
        }
    }

    // Private helper method to get the log file path and ensure the directory exists.
    private static function getLogFilePath(string $message): string
    {
        // Use a log directory within the project's storage path
        // Assumes this file is in lib/Env.php relative to the project root
        $projectRoot = dirname(__DIR__); // Go up one directory from lib/ to project root
        $logDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

        // Ensure the directory exists (mkdir on /tmp should always work)
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        // Construct the full log file path
        $logFile = $logDir . DIRECTORY_SEPARATOR . 'my_debug_log.txt';

        // If the directory is not writable, file_put_contents will likely fail with an open_basedir error.
        return $logFile;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? \getenv($key);
        if ($v === false || $v === null) return $default;
        $v = strtolower((string)$v);
        return in_array($v, ['1','true','on','yes'], true);
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? \getenv($key);
        if ($v === false || $v === null) return $default;
        return (string)$v;
    }
}