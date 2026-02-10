<?php
use NGN\Lib\Rankings\RankingService;
use PHPUnit\Framework\TestCase;

class RankingServiceTest extends TestCase
{
    public function testTopArtistsReturnsNumericTypes(): void
    {
        $svc = new RankingService(new \NGN\Lib\Config());
        $res = $svc->topArtists(10, 'daily');
        $this->assertIsArray($res);
        $this->assertArrayHasKey('items', $res);
        $this->assertArrayHasKey('interval', $res);
        $this->assertArrayHasKey('top', $res);
        $this->assertSame('daily', $res['interval']);
        $this->assertSame(10, $res['top']);
        $this->assertCount(10, $res['items']);
        $first = $res['items'][0];
        $this->assertIsInt($first['id']);
        $this->assertIsString($first['name']);
        $this->assertIsFloat($first['score']);
        $this->assertIsInt($first['rank']);
        $this->assertIsInt($first['delta']);
        $this->assertSame(1, $first['rank']);
    }

    public function testTopCapAt100(): void
    {
        $svc = new RankingService(new \NGN\Lib\Config());
        $res = $svc->topLabels(1000, 'weekly');
        $this->assertCount(100, $res['items']);
        $this->assertSame(100, $res['top']);
    }
}
