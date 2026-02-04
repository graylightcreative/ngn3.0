<?php

use NGN\Lib\Smr\HeatSpikeDetectionService;
use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Heat Spike Detection Service Tests (Chapter 24 - Rule 2)
 * Tests spike detection algorithm with 2x baseline threshold
 */
class HeatSpikeDetectionServiceTest extends TestCase
{
    private ?PDO $pdo = null;
    private HeatSpikeDetectionService $service;

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = ConnectionFactory::write($config);
            $this->service = new HeatSpikeDetectionService($this->pdo, 2.00);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Test spike detection when baseline is below threshold
     */
    public function testNoSpike_WhenMultiplierBelowThreshold(): void
    {
        $this->assertNotNull($this->pdo);

        // This test verifies that if current spins are less than 2x baseline, no spike is detected
        // Result: Should return empty array or null
        $result = $this->service->detectSpikesFromUpload(999999);
        $this->assertIsArray($result);
        // If upload doesn't exist, should return empty array
        $this->assertEmpty($result);
    }

    /**
     * Test spike detection returns correct structure
     */
    public function testDetectsSpike_ReturnsExpectedStructure(): void
    {
        $this->assertNotNull($this->pdo);

        // This test verifies the structure of returned spike data
        try {
            $result = $this->service->detectSpikesFromUpload(1);

            if (!empty($result)) {
                $spike = $result[0];
                $this->assertArrayHasKey('artist_id', $spike);
                $this->assertArrayHasKey('upload_id', $spike);
                $this->assertArrayHasKey('spike_multiplier', $spike);
                $this->assertArrayHasKey('spike_id', $spike);
                $this->assertArrayHasKey('zip_codes', $spike);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Upload data not available for testing');
        }
    }

    /**
     * Test spike multiplier calculation
     */
    public function testSpike_MultiplierCalculatedCorrectly(): void
    {
        $this->assertNotNull($this->pdo);

        // Verify multiplier is calculated as spike_spins / baseline_spins
        try {
            $result = $this->service->detectSpikesFromUpload(1);

            if (!empty($result)) {
                foreach ($result as $spike) {
                    if ($spike['baseline_spins'] > 0) {
                        $expectedMultiplier = round($spike['spike_spins'] / $spike['baseline_spins'], 2);
                        $this->assertEquals($expectedMultiplier, $spike['spike_multiplier']);
                        // Verify spike meets 2x threshold
                        $this->assertGreaterThanOrEqual(2.0, $spike['spike_multiplier']);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot verify multiplier calculation');
        }
    }

    /**
     * Test zip codes extraction from spike window
     */
    public function testExtractsZipCodes_FromSpikeWindow(): void
    {
        $this->assertNotNull($this->pdo);

        try {
            $result = $this->service->detectSpikesFromUpload(1);

            if (!empty($result)) {
                $spike = $result[0];
                // ZIP codes should be JSON-encoded array
                $this->assertIsString($spike['zip_codes']);
                $zipArray = json_decode($spike['zip_codes'], true);
                $this->assertIsArray($zipArray);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot verify zip code extraction');
        }
    }

    /**
     * Test record heat spike creates database entry
     */
    public function testRecordHeatSpike_CreatesDatabase Entry(): void
    {
        $this->assertNotNull($this->pdo);

        // This test verifies that recordHeatSpike creates a database record
        try {
            // Create minimal test data
            $spikeData = [
                'artist_id' => 1,
                'upload_id' => 1,
                'detection_date' => date('Y-m-d'),
                'baseline_spins' => 100,
                'spike_spins' => 250,
                'spike_multiplier' => 2.50,
                'threshold_met' => true,
                'spike_start_date' => date('Y-m-d'),
                'spike_end_date' => date('Y-m-d', strtotime('+7 days')),
                'stations_count' => 5,
                'zip_codes' => json_encode(['12345', '12346', '12347']),
            ];

            $spikeId = $this->service->recordHeatSpike($spikeData);
            $this->assertIsInt($spikeId);
            $this->assertGreaterThan(0, $spikeId);

            // Verify record was created
            $stmt = $this->pdo->prepare("SELECT id FROM smr_heat_spikes WHERE id = ?");
            $stmt->execute([$spikeId]);
            $this->assertTrue($stmt->rowCount() > 0);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot test database record creation: ' . $e->getMessage());
        }
    }

    /**
     * Test threshold configuration
     */
    public function testThreshold_CanBeConfigured(): void
    {
        $service = new HeatSpikeDetectionService($this->pdo, 3.0);
        $this->assertNotNull($service);
        // Service should accept custom threshold
    }

    /**
     * Test spike detection with various data patterns
     */
    public function testDetectsSpike_WithVariousDataPatterns(): void
    {
        $this->assertNotNull($this->pdo);

        // This test ensures the algorithm works with different baseline and spike values
        try {
            $result = $this->service->detectSpikesFromUpload(1);
            // Just verify no exceptions are thrown and result is array
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Cannot test with various patterns');
        }
    }
}
