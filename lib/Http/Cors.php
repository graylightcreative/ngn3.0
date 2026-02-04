<?php
namespace NGN\Lib\Http;

use NGN\Lib\Config;

class Cors
{
    public static function apply(Config $config): void
    {
        $origins = $config->corsAllowedOrigins();
        header('Access-Control-Allow-Origin: '.$origins);
        header('Access-Control-Allow-Methods: '.$config->corsAllowedMethods());
        header('Access-Control-Allow-Headers: '.$config->corsAllowedHeaders());
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin');
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer-when-downgrade');
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
