<?php
use NGN\Lib\Spins\SpinValidator;
use PHPUnit\Framework\TestCase;

class SpinValidatorEdgeTest extends TestCase
{
    public function testNotesTooLong(): void
    {
        $validator = new SpinValidator();
        $payload = [
            'station_id' => 1,
            'track_id' => 1,
            'played_at' => gmdate('c', time() - 60),
            'notes' => str_repeat('a', 1001),
        ];
        [$ok, $errors] = $validator->validate($payload);
        $this->assertFalse($ok);
        $this->assertNotEmpty($errors);
        $this->assertSame('notes', $errors[0]['field']);
    }

    public function testPlayedAtTooOld(): void
    {
        $validator = new SpinValidator();
        $oldTs = time() - (2 * 365 * 24 * 3600); // ~2 years ago
        $payload = [
            'station_id' => 1,
            'track_id' => 1,
            'played_at' => gmdate('c', $oldTs),
        ];
        [$ok, $errors] = $validator->validate($payload);
        $this->assertFalse($ok);
        $this->assertNotEmpty($errors);
        $this->assertSame('played_at', $errors[0]['field']);
    }
}
