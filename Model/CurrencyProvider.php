<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model;

use Sidelah\CurrencyConverter\Api\CurrencyProviderInterface;
use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;

class CurrencyProvider implements CurrencyProviderInterface
{
    private const ENDPOINT = 'currencies';
    private const CACHE_LIFETIME = 86400;

    /**
     * @var array<string, array{code: string, name: string, symbol: string, type: string}>|null
     */
    private ?array $currencies = null;

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
    public function getCurrencies(): array
    {
        if ($this->currencies !== null) {
            return $this->currencies;
        }

        $currencies = [];
        foreach ($this->client->get(self::ENDPOINT, [], self::CACHE_LIFETIME) as $row) {
            $code = is_array($row) ? strtoupper((string)($row['iso_code'] ?? '')) : '';
            if ($code === '') {
                continue;
            }
            $currencies[$code] = [
                'code' => $code,
                'name' => (string)($row['name'] ?? $code),
                'symbol' => (string)($row['symbol'] ?? ''),
                'type' => (string)($row['type'] ?? self::TYPE_CURRENCY),
            ];
        }

        if (!$currencies) {
            throw new ApiException(__('No currencies are currently available.'));
        }

        ksort($currencies);

        return $this->currencies = $currencies;
    }
}
