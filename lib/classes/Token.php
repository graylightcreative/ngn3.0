<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Token {

    function generateJWTToken($userId, $email) {
        $secretKey = 'NextGenNoiseWillChangeTheGame666'; // REPLACE THIS!
        $payload = [
            'uid' => $userId, // User's ID
            'email' => $email, // User's email
            'exp' => time() + 3600, // Expiration time (1 hour from now)
            'iat' => time() // Issued at time
        ];

        $jwt = JWT::encode($payload, $secretKey, 'HS256'); // Use HS256 algorithm

        return $jwt;
    }
}