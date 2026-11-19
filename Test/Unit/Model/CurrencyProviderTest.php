<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Model\CurrencyProvider;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;

class CurrencyProviderTest extends TestCase
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

    public function testNormalisesSortsAndMemoises(): void
    {
        $this->client->expects($this->once())
            ->method('get')
            ->with('currencies', [], 86400)
            ->willReturn([
                ['iso_code' => 'usd', 'name' => 'US Dollar', 'symbol' => '$', 'type' => 'currency'],
                ['iso_code' => 'BTC', 'name' => 'Bitcoin', 'symbol' => '₿', 'type' => 'crypto'],
                ['name' => 'no code'],
                'garbage',
            ]);
        $provider = new CurrencyProvider($this->client);

        $currencies = $provider->getCurrencies();
        $provider->getCurrencies();

        $this->assertSame(['BTC', 'USD'], array_keys($currencies));
        $this->assertSame(
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'type' => 'currency'],
            $currencies['USD']
        );
    }

    public function testEmptyListThrows(): void
    {
        $this->client->method('get')->willReturn([]);

        $this->expectException(ApiException::class);
        (new CurrencyProvider($this->client))->getCurrencies();
    }
}
