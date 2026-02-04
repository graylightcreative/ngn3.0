<?php
namespace NGN\Lib\Auth;

use NGN\Lib\Config;

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
        $logger->info('JWT Secret Key (validating): ' . $jwtCfg['secret']);
        if (!$this->config->featureAdmin()) {
            return [false, null, 'admin_disabled'];
        }
        $logger = LoggerFactory::create($this->config, 'api');
        $logger->info('Authorization Header: ' . $authHeader);
        if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
            return [false, null, 'missing_authorization'];
        }
        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return [false, null, 'invalid_authorization'];
        }
        try {
            $svc = new TokenService($this->config);
    
        $jwtCfg = $this->config->jwt();
        $jwtSecret = \NGN\Lib\Env::get('JWT_SECRET');
        \Firebase\JWT\JWT::$leeway = 60; // Allow for 60 seconds of clock skew
        try {
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            $claims = (array)$decoded;
        } catch (\Throwable $e) {
            return [false, null, 'invalid_token: ' . $e->getMessage()];
        }
        $logger = LoggerFactory::create($this->config, 'api');
        $logger->info('JWT Claims (validating): ' . json_encode($claims));
            $role = strtolower((string)($claims['role'] ?? ''));
            if (strpos($role, 'admin') === false) {
            $logger = LoggerFactory::create($this->config, 'api');
            $logger->info('JWT Role Check Failed: role=' . $role);

                return [false, null, 'forbidden'];
            }
            return [true, $claims, null];
        } catch (\Throwable $e) {
            return [false, null, 'invalid_token'];
        }
    }
}
