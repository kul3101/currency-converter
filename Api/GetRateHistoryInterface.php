<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Api;

use Sidelah\CurrencyConverter\Model\Exception\ApiException;
use Sidelah\CurrencyConverter\Model\HistoryRange;

interface GetRateHistoryInterface
{
    /**
     * Rate history for the pair over the range, oldest first.
     *
     * @param string $from
     * @param string $to
     * @param HistoryRange $range
     * @return list<array{date: string, rate: float}>
     * @throws ApiException
     */
    public function execute(string $from, string $to, HistoryRange $range): array;
}
