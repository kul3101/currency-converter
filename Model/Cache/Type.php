<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Type extends TagScope
{
    public const TYPE_IDENTIFIER = 'sidelah_currency_converter';
    public const CACHE_TAG = 'SIDELAH_CURRENCY_CONVERTER';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
