<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Sidelah\CurrencyConverter\Api\ConfigInterface;

class Config implements ConfigInterface
{
    private const XML_PATH_ENABLED = 'sidelah_currency_converter/general/enabled';
    private const XML_PATH_DEFAULT_FROM = 'sidelah_currency_converter/general/default_from';
    private const XML_PATH_DEFAULT_TO = 'sidelah_currency_converter/general/default_to';
    private const XML_PATH_DEFAULT_RANGE = 'sidelah_currency_converter/general/default_range';
    private const XML_PATH_BASE_URL = 'sidelah_currency_converter/api/base_url';
    private const XML_PATH_TIMEOUT = 'sidelah_currency_converter/api/timeout';
    private const XML_PATH_CACHE_LIFETIME = 'sidelah_currency_converter/api/cache_lifetime';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultFrom(): string
    {
        return $this->getCurrencyCode(self::XML_PATH_DEFAULT_FROM);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultTo(): string
    {
        return $this->getCurrencyCode(self::XML_PATH_DEFAULT_TO);
    }

    /**
     * @inheritdoc
     */
    public function getDefaultRange(): HistoryRange
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_RANGE, ScopeInterface::SCOPE_STORE);

        return HistoryRange::tryFrom($value) ?? HistoryRange::ThreeMonths;
    }

    /**
     * @inheritdoc
     */
    public function getApiBaseUrl(): string
    {
        return rtrim((string)$this->scopeConfig->getValue(self::XML_PATH_BASE_URL), '/') . '/';
    }

    /**
     * @inheritdoc
     */
    public function getTimeout(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_TIMEOUT));
    }

    /**
     * @inheritdoc
     */
    public function getCacheLifetime(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_LIFETIME));
    }

    /**
     * Read a store-scoped currency code, normalised to upper case.
     *
     * @param string $path
     * @return string
     */
    private function getCurrencyCode(string $path): string
    {
        return strtoupper(trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE)));
    }
}
