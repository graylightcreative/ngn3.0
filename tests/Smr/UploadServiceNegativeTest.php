<?php
use NGN\Lib\Smr\UploadService;
use NGN\Lib\Config;
use PHPUnit\Framework\TestCase;

class UploadServiceNegativeTest extends TestCase
{
    protected function setUp(): void
    {
        // Use a dedicated temp upload dir for tests
        putenv('UPLOAD_DIR=' . sys_get_temp_dir() . '/ngn_uploads_test_' . bin2hex(random_bytes(3)));
        putenv('UPLOAD_MAX_MB=50');
        @mkdir(getenv('UPLOAD_DIR'), 0775, true);
    }

    public function testOversizeFileIsRejected(): void
    {
        // Set max to 1 byte to force rejection
        putenv('UPLOAD_MAX_MB=0');
        $_ENV['UPLOAD_MAX_MB'] = '0';
        $tmp = tempnam(sys_get_temp_dir(), 'ngn_csv_');
        file_put_contents($tmp, "A\n"); // > 0 bytes
        $files = [
            'name' => 'tiny.csv',
            'type' => 'text/csv',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
        $svc = new UploadService(new Config());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File too large');
        $svc->handleMultipart($files, ['station_id' => 1]);
    }

    public function testBadMimeIsRejected(): void
    {
        putenv('UPLOAD_MAX_MB=5');
        putenv('UPLOAD_ALLOWED_MIME=text/csv');
        $tmp = tempnam(sys_get_temp_dir(), 'ngn_pdf_');
        file_put_contents($tmp, "%PDF-1.4 test");
        $files = [
            'name' => 'doc.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
        ];
        $svc = new UploadService(new Config());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported MIME type');
        $svc->handleMultipart($files, ['station_id' => 1]);
    }

    public function testMissingFileError(): void
    {
        $files = [
            'name' => 'missing.csv',
            'type' => 'text/csv',
            'tmp_name' => '/nonexistent',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];
        $svc = new UploadService(new Config());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File upload error');
        $svc->handleMultipart($files, ['station_id' => 1]);
    }
}
