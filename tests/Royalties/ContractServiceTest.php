<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Royalties\ContractService;

class ContractServiceTest extends TestCase
{
    public function testListContractsFiltersAndShapes(): void
    {
        $svc = new ContractService();
        $all = $svc->list();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('items', $all);
        $this->assertArrayHasKey('total', $all);
        $this->assertGreaterThan(0, $all['total']);
        $row = $all['items'][0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('artist_id', $row);
        $this->assertArrayHasKey('label_id', $row);
        $this->assertArrayHasKey('split_pct', $row);

        $filtered = $svc->list(2, null);
        foreach ($filtered['items'] as $r) {
            $this->assertSame(2, $r['label_id']);
        }

        $filtered2 = $svc->list(null, 5);
        foreach ($filtered2['items'] as $r) {
            $this->assertSame(5, $r['artist_id']);
        }
    }
}
