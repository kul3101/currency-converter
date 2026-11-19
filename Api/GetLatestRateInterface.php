<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Api;

use Sidelah\CurrencyConverter\Model\Exception\ApiException;

interface GetLatestRateInterface
{
    /**
     * Latest published rate for 1 unit of $from expressed in $to.
     *
     * @param string $from
     * @param string $to
     * @return array{date: string, rate: float}
     * @throws ApiException
     */
    public function execute(string $from, string $to): array;
}
