<?php
namespace NGN\Lib\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use NGN\Lib\Config;
use NGN\Lib\Logging\LoggerFactory;

class AdminAuth
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Validate Authorization: Bearer <token> header and ensure role=admin in token claims.
     * Returns [bool $ok, array $claims|null, string|null $error]
     */
    public function check(?string $authHeader): array
    {
        $logger = LoggerFactory::create($this->config, 'api');
        $jwtCfg = $this->config->jwt();
        
        if (!$this->config->featureAdmin()) {
            return [false, null, 'admin_disabled'];
        }

        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            return [false, null, 'missing_authorization'];
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return [false, null, 'invalid_authorization'];
        }

        $jwtSecret = $jwtCfg['secret'];
        if (empty($jwtSecret)) {
             $logger->error('JWT_SECRET is not configured');
             return [false, null, 'server_configuration_error'];
        }

        JWT::$leeway = 60; // Allow for 60 seconds of clock skew

        try {
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            $claims = (array)$decoded;
            
            $role = strtolower((string)($claims['role'] ?? ''));
            if (strpos($role, 'admin') === false) {
                $logger->warning('JWT Role Check Failed: role=' . $role);
                return [false, null, 'forbidden'];
            }
            return [true, $claims, null];
        } catch (\Throwable $e) {
            $logger->warning('JWT Decode Failed: ' . $e->getMessage());
            return [false, null, 'invalid_token: ' . $e->getMessage()];
        }
    }
}
