<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Rankings\RankingService;

class RankingEtagTest extends TestCase
{
    public function testDeterministicEtagLikeHash(): void
    {
        $svc = new RankingService(new \NGN\Lib\Config());
        $list1 = $svc->list('artist', 'daily', 1, 10, 'rank', 'asc');
        $list2 = $svc->list('artist', 'daily', 1, 10, 'rank', 'asc');
        $hash1 = sha1(json_encode(['items' => $list1['items'], 'total' => $list1['total']]));
        $hash2 = sha1(json_encode(['items' => $list2['items'], 'total' => $list2['total']]));
        $this->assertSame($hash1, $hash2, 'ETag-like hash should be stable for same params');

        $list3 = $svc->list('artist', 'weekly', 1, 10, 'rank', 'asc');
        $hash3 = sha1(json_encode(['items' => $list3['items'], 'total' => $list3['total']]));
        $this->assertNotSame($hash1, $hash3, 'Different interval should yield different hash');
    }
}
