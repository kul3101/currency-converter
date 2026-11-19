<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;
use Sidelah\CurrencyConverter\Model\GetLatestRate;

class GetLatestRateTest extends TestCase
{
    /**
     * @var FrankfurterClientInterface|MockObject|null
     */
    private ?MockObject $client = null;

    protected function setUp(): void
    {
        $this->client = $this->createMock(FrankfurterClientInterface::class);
    }

    protected function tearDown(): void
    {
        $this->client = null;
        parent::tearDown();
    }

    public function testReturnsNormalisedPoint(): void
    {
        $this->client->expects($this->once())
            ->method('get')
            ->with('rate/USD/EUR')
            ->willReturn(['date' => '2026-11-19', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 0.91174]);

        $this->assertSame(
            ['date' => '2026-11-19', 'rate' => 0.91174],
            (new GetLatestRate($this->client))->execute('USD', 'EUR')
        );
    }

    public function testThrowsOnMalformedPayload(): void
    {
        $this->client->method('get')->willReturn(['date' => '2026-11-19', 'rate' => 0]);

        $this->expectException(ApiException::class);
        (new GetLatestRate($this->client))->execute('USD', 'EUR');
    }
}
