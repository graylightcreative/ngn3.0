<?php

namespace NGN\Lib\Services\Graylight;

use Exception;

/**
 * SettleService [LEDGER]
 * 
 * NGN no longer calculates financial splits.
 * It pulls math and execution from the Graylight Ledger.
 */
class SettleService
{
    private GraylightServiceClient $client;

    public function __construct(GraylightServiceClient $client)
    {
        $this->client = $client;
    }

    /**
     * Execute a 90/10 split settlement
     * 
     * @param float $amount Gross amount in USD
     * @param string $destination Sovereign ID of the recipient
     * @return string Integrity hash of the transaction
     * @throws Exception
     */
    public function split(float $amount, string $destination): string
    {
        $response = $this->client->call('settle/split', [
            'amount' => $amount,
            'destination' => $destination,
            'split_ratio' => '90/10'
        ]);

        if (!isset($response['success']) || !$response['success']) {
            throw new Exception("Settle Split Failed: " . ($response['message'] ?? 'unknown_error'));
        }

        return $response['data']['integrity_hash'];
    }
}
