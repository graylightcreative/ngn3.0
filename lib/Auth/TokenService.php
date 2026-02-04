<?php
namespace NGN\Lib\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use NGN\Lib\Config;

class TokenService
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function issueAccessToken(array $subjectClaims = []): array
    {
        $jwtCfg = $this->config->jwt();
        $now = time();
        $exp = $now + (int)$jwtCfg['ttl'];
        $payload = array_merge([
            'iss' => $jwtCfg['iss'],
            'aud' => $jwtCfg['aud'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
        ], $subjectClaims);
        $jwtSecret = \NGN\Lib\Env::get('JWT_SECRET');
        $token = JWT::encode($payload, $jwtSecret, 'HS256');
        return [
            'token' => $token,
            'expires_in' => (int)$jwtCfg['ttl'],
        ];
    }

    /**
     * Encode a JWT token with the given claims (returns token string)
     * Alias for issueAccessToken() but returns just the token string
     */
    public function encode(array $subjectClaims = []): string
    {
        $result = $this->issueAccessToken($subjectClaims);
        return $result['token'];
    }

    public function decode(string $token): array
    {
        $jwtCfg = $this->config->jwt();
        $decoded = JWT::decode($token, new Key($jwtCfg['secret'], 'HS256'));
        // Convert stdClass to array recursively
        return json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
