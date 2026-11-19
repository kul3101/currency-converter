<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model;

use DateTimeImmutable;

enum HistoryRange: string
{
    public const GROUP_WEEK = 'week';
    public const GROUP_MONTH = 'month';

    case OneWeek = '1w';
    case OneMonth = '1m';
    case ThreeMonths = '3m';
    case SixMonths = '6m';
    case OneYear = '1y';
    case FiveYears = '5y';

    /**
     * Short label shown on the range buttons.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::OneWeek => '1W',
            self::OneMonth => '1M',
            self::ThreeMonths => '3M',
            self::SixMonths => '6M',
            self::OneYear => '1Y',
            self::FiveYears => '5Y',
        };
    }

    /**
     * First day of the range, counted back from today.
     *
     * @param DateTimeImmutable $today
     * @return DateTimeImmutable
     */
    public function getStartDate(DateTimeImmutable $today): DateTimeImmutable
    {
        return $today->modify(match ($this) {
            self::OneWeek => '-7 days',
            self::OneMonth => '-1 month',
            self::ThreeMonths => '-3 months',
            self::SixMonths => '-6 months',
            self::OneYear => '-1 year',
            self::FiveYears => '-5 years',
        });
    }

    /**
     * Frankfurter "group" parameter; long ranges are down-sampled to keep the chart readable.
     *
     * @return string|null
     */
    public function getGroup(): ?string
    {
        return match ($this) {
            self::OneYear => self::GROUP_WEEK,
            self::FiveYears => self::GROUP_MONTH,
            default => null,
        };
    }
}
