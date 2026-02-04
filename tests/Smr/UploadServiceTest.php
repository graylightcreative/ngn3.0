<?php
use NGN\Lib\Smr\UploadService;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;

class UploadServiceTest extends TestCase
{
    public function testCreateUploadReturnsRecordWithIdAndStatus(): void
    {
        $config = new Config();
        $svc = new UploadService($config);
        $rec = $svc->createUpload([
            'station_id' => 123,
            'filename' => 'test.xlsx',
            'size_bytes' => 1024,
        ]);
        $this->assertIsArray($rec);
        $this->assertArrayHasKey('id', $rec);
        $this->assertArrayHasKey('status', $rec);
        $this->assertArrayHasKey('created_at', $rec);
        $this->assertSame('received', $rec['status']);
        $this->assertStringStartsWith('upl_', $rec['id']);
    }

    public function testGetStatusReturnsCannedStatus(): void
    {
        $config = new Config();
        $svc = new UploadService($config);
        $status = $svc->getStatus('upl_20250101000000_deadbeef');
        $this->assertSame('upl_20250101000000_deadbeef', $status['id']);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('message', $status);
    }
}
