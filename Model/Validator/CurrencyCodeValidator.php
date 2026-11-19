<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model\Validator;

use Sidelah\CurrencyConverter\Api\CurrencyCodeValidatorInterface;
use Sidelah\CurrencyConverter\Api\CurrencyProviderInterface;

class CurrencyCodeValidator implements CurrencyCodeValidatorInterface
{
    private const CODE_PATTERN = '/^[A-Z0-9]{2,10}$/';

    /**
     * @param CurrencyProviderInterface $currencyProvider
     */
    public function __construct(
        private readonly CurrencyProviderInterface $currencyProvider
    ) {
    }

    /**
     * @inheritdoc
     */
    public function validate(string $code): bool
    {
        return preg_match(self::CODE_PATTERN, $code) === 1
            && isset($this->currencyProvider->getCurrencies()[$code]);
    }
}
