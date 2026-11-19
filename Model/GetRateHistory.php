<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model;

use DateTimeImmutable;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Api\GetRateHistoryInterface;

class GetRateHistory implements GetRateHistoryInterface
{
    private const ENDPOINT = 'rates';
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * @param FrankfurterClientInterface $client
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly FrankfurterClientInterface $client,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(string $from, string $to, HistoryRange $range): array
    {
        $today = DateTimeImmutable::createFromMutable($this->timezone->date(null, null, false, false));
        $query = [
            'base' => $from,
            'quotes' => $to,
            'from' => $range->getStartDate($today)->format(self::DATE_FORMAT),
        ];
        if ($range->getGroup() !== null) {
            $query['group'] = $range->getGroup();
        }

        $history = [];
        foreach ($this->client->get(self::ENDPOINT, $query) as $row) {
            if (!is_array($row) || ($row['quote'] ?? null) !== $to) {
                continue;
            }
            $date = $row['date'] ?? null;
            $rate = $row['rate'] ?? null;
            if (is_string($date) && is_numeric($rate) && (float)$rate > 0) {
                $history[] = ['date' => $date, 'rate' => (float)$rate];
            }
        }

        usort($history, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $history;
    }
}
