<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Sidelah\CurrencyConverter\Api\ConfigInterface;
use Sidelah\CurrencyConverter\Api\CurrencyProviderInterface;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;

class Index implements HttpGetActionInterface
{
    private const UNAVAILABLE_HANDLE = 'currencyconverter_index_unavailable';

    /**
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param ConfigInterface $config
     * @param CurrencyProviderInterface $currencyProvider
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly ConfigInterface $config,
        private readonly CurrencyProviderInterface $currencyProvider
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): Page|Forward
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Currency Converter'));

        try {
            $this->currencyProvider->getCurrencies();
        } catch (ApiException) {
            // Non-cacheable notice so a temporary API outage is never stored in the full page cache.
            $page->addHandle(self::UNAVAILABLE_HANDLE);
        }

        return $page;
    }
}
