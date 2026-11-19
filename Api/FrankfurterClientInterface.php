<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Api;

use Sidelah\CurrencyConverter\Model\Exception\ApiException;

interface FrankfurterClientInterface
{
    /**
     * GET a Frankfurter endpoint and return the decoded JSON body.
     *
     * @param string $path Path relative to the configured base URL, e.g. "rate/USD/EUR"
     * @param array<string,string|int> $query
     * @param int|null $cacheLifetime Seconds; null uses the configured lifetime, 0 disables caching
     * @return array<mixed>
     * @throws ApiException
     */
    public function get(string $path, array $query = [], ?int $cacheLifetime = null): array;
}
