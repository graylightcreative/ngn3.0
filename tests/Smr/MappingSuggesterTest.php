<?php
use NGN\Lib\Smr\MappingSuggester;
use PHPUnit\Framework\TestCase;

class MappingSuggesterTest extends TestCase
{
    public function testSuggestsCommonHeaders(): void
    {
        $s = new MappingSuggester();
        $headers = ['Artist', 'Track Title', 'Spins', 'Report Date'];
        $res = $s->suggest($headers);
        $this->assertArrayHasKey('artist', $res);
        $this->assertArrayHasKey('track', $res);
        $this->assertArrayHasKey('spins', $res);
        $this->assertArrayHasKey('date', $res);
        $this->assertSame('Artist', $res['artist']['header']);
        $this->assertSame('Track Title', $res['track']['header']);
    }

    public function testReturnsEmptyForUnrelatedHeaders(): void
    {
        $s = new MappingSuggester();
        $headers = ['Foo', 'Bar', 'Baz'];
        $res = $s->suggest($headers);
        $this->assertIsArray($res);
        $this->assertEmpty($res);
    }
}
