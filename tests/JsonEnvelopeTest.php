<?php
use NGN\Lib\Http\Json;
use PHPUnit\Framework\TestCase;

class JsonEnvelopeTest extends TestCase
{
    public function testEnvelopeStructure(): void
    {
        $payload = Json::envelope(['ok' => true], ['page' => 1], []);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertArrayHasKey('errors', $payload);
        $this->assertSame(['ok' => true], $payload['data']);
        $this->assertSame(['page' => 1], $payload['meta']);
        $this->assertSame([], $payload['errors']);
    }
}
