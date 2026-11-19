<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Raised when the Frankfurter API is unreachable or returns an unusable response.
 */
class ApiException extends LocalizedException
{
}
