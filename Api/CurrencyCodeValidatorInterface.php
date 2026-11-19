<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Api;

use Sidelah\CurrencyConverter\Model\Exception\ApiException;

interface CurrencyCodeValidatorInterface
{
    /**
     * Whether the code is well-formed and supported by Frankfurter.
     *
     * @param string $code
     * @return bool
     * @throws ApiException When the supported currency list cannot be loaded
     */
    public function validate(string $code): bool;
}
