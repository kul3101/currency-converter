<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\ViewModel;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Sidelah\CurrencyConverter\Api\ConfigInterface;
use Sidelah\CurrencyConverter\Api\CurrencyProviderInterface;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;
use Sidelah\CurrencyConverter\Model\HistoryRange;

class Converter implements ArgumentInterface
{
    private const RATE_ROUTE = 'currency-converter/rate';
    private const JS_COMPONENT = 'Sidelah_CurrencyConverter/js/converter';

    /**
     * @param CurrencyProviderInterface $currencyProvider
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly CurrencyProviderInterface $currencyProvider,
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Currencies grouped under a translated label, for <optgroup> rendering.
     *
     * @return array<string, list<array{code: string, name: string, symbol: string, type: string}>>
     */
    public function getCurrencyGroups(): array
    {
        $groups = [
            CurrencyProviderInterface::TYPE_CURRENCY => [],
            CurrencyProviderInterface::TYPE_METAL => [],
            CurrencyProviderInterface::TYPE_CRYPTO => [],
        ];
        foreach ($this->getCurrencies() as $currency) {
            $groups[$currency['type']][] = $currency;
        }

        $result = [];
        foreach (array_filter($groups) as $type => $currencies) {
            $result[$this->getGroupLabel($type)] = $currencies;
        }

        return $result;
    }

    /**
     * Initially selected "from" code: the configured code, else the store's current currency.
     *
     * @return string
     */
    public function getDefaultFrom(): string
    {
        $configured = $this->config->getDefaultFrom();

        return $this->resolveSupported($configured !== '' ? $configured : $this->getStoreCurrency(), '');
    }

    /**
     * Initially selected "to" code, never the same as the "from" code.
     *
     * @return string
     */
    public function getDefaultTo(): string
    {
        return $this->resolveSupported($this->config->getDefaultTo(), $this->getDefaultFrom());
    }

    /**
     * Available chart ranges.
     *
     * @return HistoryRange[]
     */
    public function getRanges(): array
    {
        return HistoryRange::cases();
    }

    /**
     * Initially selected chart range.
     *
     * @return HistoryRange
     */
    public function getDefaultRange(): HistoryRange
    {
        return $this->config->getDefaultRange();
    }

    /**
     * Value for the data-mage-init attribute that boots the converter widget.
     *
     * @return string
     */
    public function getMageInit(): string
    {
        return (string)$this->serializer->serialize([
            self::JS_COMPONENT => [
                'rateUrl' => $this->urlBuilder->getUrl(self::RATE_ROUTE),
                'from' => $this->getDefaultFrom(),
                'to' => $this->getDefaultTo(),
                'range' => $this->getDefaultRange()->value,
                'ranges' => array_map(static fn (HistoryRange $r): string => $r->value, HistoryRange::cases()),
            ],
        ]);
    }

    /**
     * Currencies, or an empty list when the API is unavailable.
     *
     * @return array<string, array{code: string, name: string, symbol: string, type: string}>
     */
    private function getCurrencies(): array
    {
        try {
            return $this->currencyProvider->getCurrencies();
        } catch (ApiException) {
            return [];
        }
    }

    /**
     * Translated label for a currency type.
     *
     * @param string $type
     * @return string
     */
    private function getGroupLabel(string $type): string
    {
        return (string)match ($type) {
            CurrencyProviderInterface::TYPE_CURRENCY => __('Currencies'),
            CurrencyProviderInterface::TYPE_METAL => __('Precious Metals'),
            CurrencyProviderInterface::TYPE_CRYPTO => __('Cryptocurrencies'),
            default => ucfirst($type),
        };
    }

    /**
     * Current display currency of the store being rendered.
     *
     * @return string
     */
    private function getStoreCurrency(): string
    {
        $store = $this->storeManager->getStore();

        return $store instanceof Store ? (string)$store->getCurrentCurrencyCode() : '';
    }

    /**
     * Return $preferred when supported and different from $exclude, else the first supported code that is.
     *
     * @param string $preferred
     * @param string $exclude
     * @return string
     */
    private function resolveSupported(string $preferred, string $exclude): string
    {
        $currencies = $this->getCurrencies();
        if ($preferred !== $exclude && isset($currencies[$preferred])) {
            return $preferred;
        }
        foreach (array_keys($currencies) as $code) {
            if ($code !== $exclude) {
                return $code;
            }
        }

        return '';
    }
}
