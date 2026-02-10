<?php

namespace NGN\Lib\Services\Graylight;

use Exception;

/**
 * VaultStorageService [VAULT]
 * 
 * NGN no longer writes bytes to local storage. 
 * It pulls upload URLs from Graylight and stores only vault IDs.
 */
class VaultStorageService
{
    private GraylightServiceClient $client;

    public function __construct(GraylightServiceClient $client)
    {
        $this->client = $client;
    }

    /**
     * Initiate a secure upload handshake
     * 
     * @param string $filename
     * @param string $mimeType
     * @return array{upload_url:string, vault_id:string}
     * @throws Exception
     */
    public function getUploadUrl(string $filename, string $mimeType): array
    {
        $response = $this->client->call('storage/upload', [
            'filename' => $filename,
            'mime_type' => $mimeType
        ]);

        if (!isset($response['success']) || !$response['success']) {
            throw new Exception("Vault Handshake Failed: " . ($response['message'] ?? 'unknown_error'));
        }

        return [
            'upload_url' => $response['data']['upload_url'],
            'vault_id' => $response['data']['vault_id']
        ];
    }
}
