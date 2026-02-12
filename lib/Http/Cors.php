<?php
namespace NGN\Lib\Http;

use NGN\Lib\Config;

class Cors
{
    public static function apply(Config $config): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Fleet Domain Validation
        $allowedOrigins = [
            'https://nextgennoise.com',
            'https://www.nextgennoise.com',
            'https://api.nextgennoise.com',
            'https://beta.nextgennoise.com'
        ];

        // Authorize Graylight Fleet Subdomains
        if (preg_match('/^https:\/\/.*\.graylightcreative\.com$/', $origin) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } else {
            // Default Fallback
            header('Access-Control-Allow-Origin: https://nextgennoise.com');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Autorization, X-Requested-With, X-Fleet-Token');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');
        
        // Security moats
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    public static function handlePreflight(Request $req, Config $config): bool
    {
        if ($req->method() === 'OPTIONS') {
            self::apply($config);
            http_response_code(204);
            return true;
        }
        return false;
    }
}
