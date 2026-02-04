<?php
use NGN\Lib\Smr\HeaderDetector;
use PHPUnit\Framework\TestCase;

class HeaderDetectorTest extends TestCase
{
    public function testDetectCsvHeaders(): void
    {
        $fixture = __DIR__.'/../../tests_fixtures/sample.csv';
        if (!is_dir(dirname($fixture))) {
            @mkdir(dirname($fixture), 0775, true);
        }
        // Create a small CSV fixture
        file_put_contents($fixture, "Artist,Track,Spins,Date\nFoo,Bar,10,2025-01-01\n");

        $detector = new HeaderDetector();
        $res = $detector->detectHeaders($fixture);
        $this->assertIsArray($res);
        $this->assertSame(['Artist','Track','Spins','Date'], $res['headers']);
        $this->assertSame(1, $res['rows_sampled']);
        $this->assertArrayHasKey('sample_rows', $res);
        $this->assertNotEmpty($res['sample_rows']);
        $this->assertSame('Foo', $res['sample_rows'][0]['Artist'] ?? null);
    }
}
