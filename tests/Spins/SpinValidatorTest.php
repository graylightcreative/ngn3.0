<?php
use NGN\Lib\Spins\SpinValidator;
use PHPUnit\Framework\TestCase;

class SpinValidatorTest extends TestCase
{
    public function testValidSpin(): void
    {
        $validator = new SpinValidator();
        $payload = [
            'station_id' => 10,
            'track_id' => 123,
            'played_at' => gmdate('c', time() - 60),
        ];
        [$ok, $errors, $norm] = $validator->validate($payload);
        $this->assertTrue($ok, 'Expected valid spin payload');
        $this->assertSame([], $errors);
        $this->assertSame(10, $norm['station_id']);
        $this->assertSame(123, $norm['track_id']);
        $this->assertArrayHasKey('played_at_epoch', $norm);
        $this->assertArrayHasKey('played_at_iso', $norm);
    }

    public function testInvalidSpinFutureTime(): void
    {
        $validator = new SpinValidator();
        $payload = [
            'station_id' => 10,
            'track_id' => 123,
            'played_at' => gmdate('c', time() + 600), // 10 minutes in future
        ];
        [$ok, $errors] = $validator->validate($payload);
        $this->assertFalse($ok);
        $this->assertNotEmpty($errors);
        $this->assertSame('played_at', $errors[0]['field']);
    }

    public function testMissingTrackId(): void
    {
        $validator = new SpinValidator();
        $payload = [
            'station_id' => 10,
            'played_at' => gmdate('c'),
        ];
        [$ok, $errors] = $validator->validate($payload);
        $this->assertFalse($ok);
        $this->assertNotEmpty($errors);
        $this->assertSame('track_id', $errors[0]['field']);
    }
}
