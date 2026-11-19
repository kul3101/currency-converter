<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model;

use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Api\GetLatestRateInterface;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;

class GetLatestRate implements GetLatestRateInterface
{
    private const ENDPOINT = 'rate/%s/%s';

    /**
     * @param FrankfurterClientInterface $client
     */
    public function __construct(
        private readonly FrankfurterClientInterface $client
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(string $from, string $to): array
    {
        $row = $this->client->get(sprintf(self::ENDPOINT, rawurlencode($from), rawurlencode($to)));
        $date = $row['date'] ?? null;
        $rate = $row['rate'] ?? null;
        if (!is_string($date) || !is_numeric($rate) || (float)$rate <= 0) {
            throw new ApiException(__('No exchange rate is available for %1 to %2.', $from, $to));
        }

        return ['date' => $date, 'rate' => (float)$rate];
    }
}
