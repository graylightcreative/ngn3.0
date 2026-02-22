<?php
namespace NGN\Lib\Fans;

/**
 * Mock Spark Service for Testing
 * Namespaced to match TipService type-hint fallback
 */
class MockSparkService {
    public function charge($userId, $amount, $description) {
        return ['success' => true, 'transaction_id' => uniqid()];
    }
}
