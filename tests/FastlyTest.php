<?php

// tests/FastlyTest.php

use PHPUnit\Framework\TestCase;

class FastlyTest extends TestCase
{
    private $fastly;
    private $mockCurlHandler;

    protected function setUp(): void
    {
        // Create the mock CurlHandler
        $this->mockCurlHandler = $this->createMock(CurlHandler::class);

        // Create the instance of Fastly and inject the mock CurlHandler
        $this->fastly = new Fastly();
        $this->fastly->setCurlHandler($this->mockCurlHandler);
    }

    public function testCreateDomain()
    {
        $domainName = 'example.com';
        $response = json_encode(['name' => $domainName]);

        // Configure the mock to return the expected response
        $this->mockCurlHandler->method('execute')
            ->willReturn($response);

        // Perform the createDomain operation
        $result = $this->fastly->createDomain($domainName);

        // Assertions
        $this->assertNotFalse($result, "Domain creation failed");
        $this->assertArrayHasKey('name', $result, "Response does not have 'name'");
        $this->assertEquals($domainName, $result['name'], "Domain name does not match");
    }
}