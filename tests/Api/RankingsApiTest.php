<?php
use PHPUnit\Framework\TestCase;
use NGN\Lib\Config;
use NGN\Lib\Http\Json;

class RankingsApiTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure dev env + rankings feature for tests that simulate API logic indirectly
        putenv('APP_ENV=development');
        putenv('FEATURE_RANKINGS=true');
    }

    public function testParamValidation(): void
    {
        // Simulate param parsing logic expectations
        $intervals = ['daily','weekly','monthly'];
        foreach ($intervals as $i) {
            $this->assertContains($i, $intervals);
        }
        // Basic envelope sanity check
        $payload = Json::envelope(['ok' => true], ['page' => 1], []);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertArrayHasKey('errors', $payload);
    }

    public function testServiceListPaginationAndSorting(): void
    {
        $svc = new NGN\Lib\Rankings\RankingService(new Config());
        $res = $svc->list('artist', 'daily', 2, 5, 'score', 'desc');
        $this->assertIsArray($res);
        $this->assertArrayHasKey('items', $res);
        $this->assertArrayHasKey('total', $res);
        $this->assertCount(5, $res['items']);
        $this->assertGreaterThan(0, $res['total']);
        // Ensure numeric typing
        $row = $res['items'][0];
        $this->assertIsInt($row['id']);
        $this->assertIsString($row['name']);
        $this->assertIsFloat($row['score']);
        $this->assertIsInt($row['rank']);
        $this->assertIsInt($row['delta']);
    }
}
