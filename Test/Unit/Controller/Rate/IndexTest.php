<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Controller\Rate;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sidelah\CurrencyConverter\Api\ConfigInterface;
use Sidelah\CurrencyConverter\Api\CurrencyCodeValidatorInterface;
use Sidelah\CurrencyConverter\Api\GetLatestRateInterface;
use Sidelah\CurrencyConverter\Api\GetRateHistoryInterface;
use Sidelah\CurrencyConverter\Controller\Rate\Index;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;
use Sidelah\CurrencyConverter\Model\HistoryRange;

class IndexTest extends TestCase
{
    /**
     * @var RequestInterface|MockObject|null
     */
    private ?MockObject $request = null;

    /**
     * @var JsonResult|MockObject|null
     */
    private ?MockObject $result = null;

    /**
     * @var CurrencyCodeValidatorInterface|MockObject|null
     */
    private ?MockObject $codeValidator = null;

    /**
     * @var GetLatestRateInterface|MockObject|null
     */
    private ?MockObject $getLatestRate = null;

    /**
     * @var GetRateHistoryInterface|MockObject|null
     */
    private ?MockObject $getRateHistory = null;

    /**
     * @var Index|null
     */
    private ?Index $controller = null;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->result = $this->createMock(JsonResult::class);
        $this->result->method('setHttpResponseCode')->willReturnSelf();
        $this->result->method('setData')->willReturnSelf();
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($this->result);

        $config = $this->createMock(ConfigInterface::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getDefaultRange')->willReturn(HistoryRange::ThreeMonths);

        $this->codeValidator = $this->createMock(CurrencyCodeValidatorInterface::class);
        $this->getLatestRate = $this->createMock(GetLatestRateInterface::class);
        $this->getRateHistory = $this->createMock(GetRateHistoryInterface::class);

        $this->controller = new Index(
            $this->request,
            $jsonFactory,
            $config,
            $this->codeValidator,
            $this->getLatestRate,
            $this->getRateHistory
        );
    }

    protected function tearDown(): void
    {
        $this->request = null;
        $this->result = null;
        $this->codeValidator = null;
        $this->getLatestRate = null;
        $this->getRateHistory = null;
        $this->controller = null;
        parent::tearDown();
    }

    private function withParams(array $params): void
    {
        $this->request->method('getParam')
            ->willReturnCallback(static fn (string $key) => $params[$key] ?? null);
    }

    public function testReturnsRateAndHistoryForValidPair(): void
    {
        $this->withParams(['from' => ' usd', 'to' => 'EUR', 'range' => '1w']);
        $this->codeValidator->method('validate')->willReturn(true);
        $this->getLatestRate->method('execute')->with('USD', 'EUR')
            ->willReturn(['date' => '2026-11-19', 'rate' => 0.9]);
        $this->getRateHistory->method('execute')->with('USD', 'EUR', HistoryRange::OneWeek)
            ->willReturn([['date' => '2026-11-18', 'rate' => 0.91]]);

        $this->result->expects($this->never())->method('setHttpResponseCode');
        $this->result->expects($this->once())->method('setData')->with([
            'from' => 'USD',
            'to' => 'EUR',
            'range' => '1w',
            'rate' => 0.9,
            'date' => '2026-11-19',
            'history' => [['date' => '2026-11-18', 'rate' => 0.91]],
        ]);

        $this->controller->execute();
    }

    public function testUnknownRangeFallsBackToDefault(): void
    {
        $this->withParams(['from' => 'USD', 'to' => 'EUR', 'range' => '99y']);
        $this->codeValidator->method('validate')->willReturn(true);
        $this->getLatestRate->method('execute')->willReturn(['date' => '2026-11-19', 'rate' => 0.9]);
        $this->getRateHistory->expects($this->once())
            ->method('execute')
            ->with('USD', 'EUR', HistoryRange::ThreeMonths)
            ->willReturn([]);

        $this->controller->execute();
    }

    public function testRejectsInvalidCodeWithoutCallingApi(): void
    {
        $this->withParams(['from' => 'USD', 'to' => 'XYZ']);
        $this->codeValidator->method('validate')
            ->willReturnCallback(static fn (string $code): bool => $code === 'USD');
        $this->getLatestRate->expects($this->never())->method('execute');
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(400);

        $this->controller->execute();
    }

    public function testUpstreamFailureReturns503WithSafeMessage(): void
    {
        $this->withParams(['from' => 'USD', 'to' => 'EUR']);
        $this->codeValidator->method('validate')->willReturn(true);
        $this->getLatestRate->method('execute')
            ->willThrowException(new ApiException(__('internal detail that must not leak')));
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(503);
        $this->result->expects($this->once())->method('setData')->with([
            'error' => true,
            'message' => 'Unable to load the exchange rate. Please try again.',
        ]);

        $this->controller->execute();
    }
}
