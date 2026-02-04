<?php
use NGN\Lib\Smr\UploadService;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;

class UploadPreviewTest extends TestCase
{
    public function testGeneratePreviewCreatesLedgerFields(): void
    {
        // Prepare a temp CSV file simulating an upload
        $tmp = tempnam(sys_get_temp_dir(), 'ngn_csv_');
        file_put_contents($tmp, "Artist,Track,Spins,Date\nFoo,Bar,10,2025-01-01\n");
        $files = [
            'name' => 'sample.csv',
            'type' => 'text/csv',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
        // Ensure upload dir exists to avoid permission issues during tests
        putenv('UPLOAD_DIR=' . __DIR__ . '/../../storage_test/uploads');

        $config = new Config();
        $svc = new UploadService($config);
        $rec = $svc->handleMultipart($files, ['station_id' => 1]);

        $this->assertArrayHasKey('id', $rec);
        $id = $rec['id'];

        $after = $svc->generatePreview($id);
        $this->assertSame('preview_ready', $after['status']);
        $this->assertTrue($after['preview_available']);
        $this->assertArrayHasKey('preview_path', $after);
        $this->assertIsInt($after['rows_count']);

        $status = $svc->getStatus($id);
        $this->assertTrue($status['preview_available']);
        $this->assertGreaterThanOrEqual(0, $status['rows_count']);
    }
}
