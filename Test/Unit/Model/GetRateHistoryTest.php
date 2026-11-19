<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Model\GetRateHistory;
use Sidelah\CurrencyConverter\Model\HistoryRange;

class GetRateHistoryTest extends TestCase
{
    /**
     * @var FrankfurterClientInterface|MockObject|null
     */
    private ?MockObject $client = null;

    /**
     * @var GetRateHistory|null
     */
    private ?GetRateHistory $getRateHistory = null;

    protected function setUp(): void
    {
        $this->client = $this->createMock(FrankfurterClientInterface::class);
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(
            static fn (): \DateTime => new \DateTime('2026-11-19', new \DateTimeZone('UTC'))
        );
        $this->getRateHistory = new GetRateHistory($this->client, $timezone);
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->getRateHistory = null;
        parent::tearDown();
    }

    public function testBuildsQuerySortsAndDropsBadRows(): void
    {
        $this->client->expects($this->once())
            ->method('get')
            ->with('rates', ['base' => 'USD', 'quotes' => 'EUR', 'from' => '2025-11-19', 'group' => 'week'])
            ->willReturn([
                ['date' => '2026-11-12', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 0.92],
                ['date' => '2025-11-19', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 0.86],
                ['date' => '2026-01-01', 'base' => 'USD', 'quote' => 'GBP', 'rate' => 0.75],
                ['date' => '2026-02-01', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 'n/a'],
                'garbage',
            ]);

        $this->assertSame(
            [
                ['date' => '2025-11-19', 'rate' => 0.86],
                ['date' => '2026-11-12', 'rate' => 0.92],
            ],
            $this->getRateHistory->execute('USD', 'EUR', HistoryRange::OneYear)
        );
    }

    public function testOmitsGroupForShortRanges(): void
    {
        $this->client->expects($this->once())
            ->method('get')
            ->with('rates', ['base' => 'GBP', 'quotes' => 'JPY', 'from' => '2026-11-12'])
            ->willReturn([]);

        $this->assertSame([], $this->getRateHistory->execute('GBP', 'JPY', HistoryRange::OneWeek));
    }
}
