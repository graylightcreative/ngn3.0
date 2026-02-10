<?php

namespace NGN\Lib\Services\Graylight;

use Exception;

/**
 * IdentityService [BEACON]
 * 
 * Weld NGN identity to Graylight Sovereign Auth.
 * NGN no longer owns passwords; it pulls verification from the Mothership.
 */
class IdentityService
{
    private GraylightServiceClient $client;

    public function __construct(GraylightServiceClient $client)
    {
        $this->client = $client;
    }

    /**
     * Verify credentials against Graylight Beacon
     * 
     * @param string $email
     * @param string $password
     * @return array{success:bool, sovereign_id:string|null, jwt:string|null, error:string|null}
     */
    public function verify(string $email, string $password): array
    {
        try {
            $response = $this->client->call('auth/verify', [
                'email' => $email,
                'password' => $password
            ]);

            if (isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'sovereign_id' => $response['data']['sovereign_id'] ?? null,
                    'jwt' => $response['data']['jwt'] ?? null,
                    'error' => null
                ];
            }

            return [
                'success' => false,
                'sovereign_id' => null,
                'jwt' => null,
                'error' => $response['message'] ?? 'authentication_failed'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'sovereign_id' => null,
                'jwt' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}
