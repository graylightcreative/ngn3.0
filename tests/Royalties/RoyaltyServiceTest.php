<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Royalties\RoyaltyService;

class RoyaltyServiceTest extends TestCase
{
    public function testStatementsFiltersAndShapes(): void
    {
        $svc = new RoyaltyService();
        $all = $svc->statements();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('items', $all);
        $this->assertArrayHasKey('total', $all);
        $this->assertGreaterThan(0, $all['total']);
        $row = $all['items'][0];
        $this->assertArrayHasKey('royalty_amount', $row);
        $this->assertArrayHasKey('currency', $row);

        $f1 = $svc->statements(2, null, null);
        foreach ($f1['items'] as $r) {
            $this->assertSame(2, $r['label_id']);
        }

        $f2 = $svc->statements(null, 3, null);
        foreach ($f2['items'] as $r) {
            $this->assertSame(3, $r['artist_id']);
        }

        $f3 = $svc->statements(null, null, '2025-Q2');
        foreach ($f3['items'] as $r) {
            $this->assertSame('2025-Q2', $r['period']);
        }
    }
}
