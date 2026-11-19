<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Controller\Rate;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use Sidelah\CurrencyConverter\Api\ConfigInterface;
use Sidelah\CurrencyConverter\Api\CurrencyCodeValidatorInterface;
use Sidelah\CurrencyConverter\Api\GetLatestRateInterface;
use Sidelah\CurrencyConverter\Api\GetRateHistoryInterface;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;
use Sidelah\CurrencyConverter\Model\HistoryRange;

/**
 * JSON endpoint: /currency-converter/rate?from=USD&to=EUR&range=3m
 */
class Index implements HttpGetActionInterface
{
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_SERVICE_UNAVAILABLE = 503;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param ConfigInterface $config
     * @param CurrencyCodeValidatorInterface $codeValidator
     * @param GetLatestRateInterface $getLatestRate
     * @param GetRateHistoryInterface $getRateHistory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ConfigInterface $config,
        private readonly CurrencyCodeValidatorInterface $codeValidator,
        private readonly GetLatestRateInterface $getLatestRate,
        private readonly GetRateHistoryInterface $getRateHistory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): JsonResult
    {
        if (!$this->config->isEnabled()) {
            return $this->error(__('The currency converter is disabled.'), self::HTTP_NOT_FOUND);
        }

        $from = strtoupper(trim((string)$this->request->getParam('from')));
        $to = strtoupper(trim((string)$this->request->getParam('to')));
        $range = HistoryRange::tryFrom((string)$this->request->getParam('range'))
            ?? $this->config->getDefaultRange();

        try {
            if (!$this->codeValidator->validate($from) || !$this->codeValidator->validate($to)) {
                return $this->error(__('Unsupported currency.'), self::HTTP_BAD_REQUEST);
            }
            $latest = $this->getLatestRate->execute($from, $to);
            $history = $this->getRateHistory->execute($from, $to, $range);
        } catch (ApiException) {
            return $this->error(
                __('Unable to load the exchange rate. Please try again.'),
                self::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return $this->jsonFactory->create()->setData([
            'from' => $from,
            'to' => $to,
            'range' => $range->value,
            'rate' => $latest['rate'],
            'date' => $latest['date'],
            'history' => $history,
        ]);
    }

    /**
     * Build an error response with a fixed, user-safe message.
     *
     * @param Phrase $message
     * @param int $status
     * @return JsonResult
     */
    private function error(Phrase $message, int $status): JsonResult
    {
        return $this->jsonFactory->create()
            ->setHttpResponseCode($status)
            ->setData(['error' => true, 'message' => (string)$message]);
    }
}
