<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Model;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sidelah\CurrencyConverter\Model\HistoryRange;

class HistoryRangeTest extends TestCase
{
    /**
     * @dataProvider rangeProvider
     */
    public function testStartDateAndGrouping(HistoryRange $range, string $expectedStart, ?string $expectedGroup): void
    {
        $today = new DateTimeImmutable('2026-11-19');

        $this->assertSame($expectedStart, $range->getStartDate($today)->format('Y-m-d'));
        $this->assertSame($expectedGroup, $range->getGroup());
    }

    public static function rangeProvider(): array
    {
        return [
            '1w' => [HistoryRange::OneWeek, '2026-11-12', null],
            '1m' => [HistoryRange::OneMonth, '2026-10-19', null],
            '3m' => [HistoryRange::ThreeMonths, '2026-08-19', null],
            '6m' => [HistoryRange::SixMonths, '2026-05-19', null],
            '1y' => [HistoryRange::OneYear, '2025-11-19', 'week'],
            '5y' => [HistoryRange::FiveYears, '2021-11-19', 'month'],
        ];
    }
}
