<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Api;

use Sidelah\CurrencyConverter\Model\Exception\ApiException;

interface CurrencyProviderInterface
{
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_METAL = 'metal';
    public const TYPE_CRYPTO = 'crypto';

    /**
     * All currencies Frankfurter supports, keyed and sorted by code.
     *
     * @return array<string, array{code: string, name: string, symbol: string, type: string}>
     * @throws ApiException
     */
    public function getCurrencies(): array;
}
