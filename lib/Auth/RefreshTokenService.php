<?php
namespace NGN\Lib\Auth;

use NGN\Lib\Config;

/**
 * Dev-only Refresh Token service backed by a JSON ledger file.
 * Not for production use. In production, prefer DB/Redis with rotation and device binding.
 */
class RefreshTokenService
{
    private Config $config;
    private string $ledgerPath;
    private int $ttl; // seconds

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->ledgerPath = getenv('REFRESH_TOKENS_LEDGER_PATH') ?: (dirname($config->usersLedgerPath()) . '/refresh_tokens.json');
        $jwt = $config->jwt();
        $this->ttl = (int)($jwt['refresh_ttl'] ?? 1209600);
    }

    /**
     * Issue a new refresh token for subject + role. Returns [token, expires_in].
     */
    public function issue(string $subject, string $role): array
    {
        $now = time();
        $exp = $now + $this->ttl;
        $token = 'rft_' . bin2hex(random_bytes(24));
        $entry = [
            'token' => $token,
            'sub' => $subject,
            'role' => $role,
            'issued_at' => $now,
            'expires_at' => $exp,
            'revoked' => false,
        ];
        $this->append($entry);
        $this->pruneExpired(5000); // keep file from growing unbounded in dev
        return ['token' => $token, 'expires_in' => $this->ttl];
    }

    /**
     * Validate a refresh token, returning [ok, sub, role, error].
     */
    public function validate(string $token): array
    {
        if ($token === '') return [false, null, null, 'empty'];
        $all = $this->readAll();
        foreach ($all as $e) {
            if (($e['token'] ?? '') === $token) {
                if (!empty($e['revoked'])) return [false, null, null, 'revoked'];
                if ((int)($e['expires_at'] ?? 0) < time()) return [false, null, null, 'expired'];
                return [true, (string)($e['sub'] ?? ''), (string)($e['role'] ?? ''), null];
            }
        }
        return [false, null, null, 'not_found'];
    }

    /** Revoke a refresh token (idempotent). Returns true if found and marked revoked or already revoked. */
    public function revoke(string $token): bool
    {
        $changed = false;
        $all = $this->readAll();
        foreach ($all as &$e) {
            if (($e['token'] ?? '') === $token) {
                if (empty($e['revoked'])) {
                    $e['revoked'] = true;
                    $changed = true;
                } else {
                    $changed = true; // already revoked counts as success
                }
            }
        }
        if ($changed) {
            $this->writeAll($all);
        }
        return $changed;
    }

    private function append(array $entry): void
    {
        $dir = dirname($this->ledgerPath);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $all = $this->readAll();
        $all[] = $entry;
        $this->writeAll($all);
    }

    /**
     * Remove expired entries when file grows beyond a soft threshold.
     */
    private function pruneExpired(int $softMax): void
    {
        $all = $this->readAll();
        if (count($all) <= $softMax) return;
        $now = time();
        $kept = array_values(array_filter($all, function ($e) use ($now) {
            $exp = (int)($e['expires_at'] ?? 0);
            $rev = !empty($e['revoked']);
            return $exp >= $now && !$rev;
        }));
        $this->writeAll($kept);
    }

    private function readAll(): array
    {
        if (!is_file($this->ledgerPath)) return [];
        $json = file_get_contents($this->ledgerPath) ?: '[]';
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    private function writeAll(array $entries): void
    {
        @file_put_contents($this->ledgerPath, json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
