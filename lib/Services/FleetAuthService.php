<?php
namespace NGN\Lib\Services;

class FleetAuthService {
    private const FLEET_DB_CONFIG = [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'Starr!1',
        'name' => 'graylight_nexus'
    ];

    public static function checkHandshake(): ?array {
        $token = $_COOKIE['fleet_token'] ?? null;
        if (!$token) return null;

        try {
            $dsn = "mysql:host=" . self::FLEET_DB_CONFIG['host'] . ";dbname=" . self::FLEET_DB_CONFIG['name'] . ";charset=utf8mb4";
            $pdo = new \PDO($dsn, self::FLEET_DB_CONFIG['user'], self::FLEET_DB_CONFIG['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]);

            $stmt = $pdo->prepare('SELECT email FROM fleet_sessions WHERE id = ? AND expires_at > NOW()');
            $stmt->execute([$token]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            error_log('FLEET_AUTH_ERROR: ' . $e->getMessage());
            return null;
        }
    }
}
