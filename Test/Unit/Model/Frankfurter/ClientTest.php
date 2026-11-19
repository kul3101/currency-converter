<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Test\Unit\Model\Frankfurter;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sidelah\CurrencyConverter\Api\ConfigInterface;
use Sidelah\CurrencyConverter\Model\Cache\Type as CacheType;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;
use Sidelah\CurrencyConverter\Model\Frankfurter\Client;

class ClientTest extends TestCase
{
    /**
     * @var Curl|MockObject|null
     */
    private ?MockObject $curl = null;

    /**
     * @var FrontendInterface|MockObject|null
     */
    private ?MockObject $cache = null;

    /**
     * @var Client|null
     */
    private ?Client $client = null;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($this->curl);

        $config = $this->createMock(ConfigInterface::class);
        $config->method('getApiBaseUrl')->willReturn('https://api.frankfurter.dev/v2/');
        $config->method('getTimeout')->willReturn(5);
        $config->method('getCacheLifetime')->willReturn(3600);

        $this->cache = $this->createMock(FrontendInterface::class);
        $cacheState = $this->createMock(StateInterface::class);
        $cacheState->method('isEnabled')->willReturn(true);

        $this->client = new Client(
            $curlFactory,
            new Json(),
            $config,
            $this->cache,
            $cacheState,
            $this->createMock(LoggerInterface::class)
        );
    }

    protected function tearDown(): void
    {
        $this->curl = null;
        $this->cache = null;
        $this->client = null;
        parent::tearDown();
    }

    public function testFetchesAndCachesSuccessfulResponse(): void
    {
        $body = '{"date":"2026-11-19","base":"USD","quote":"EUR","rate":0.9}';
        $this->cache->method('load')->willReturn(false);
        $this->curl->expects($this->once())
            ->method('get')
            ->with('https://api.frankfurter.dev/v2/rates?base=USD&quotes=EUR');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn($body);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($body, $this->isType('string'), [CacheType::CACHE_TAG], 3600);

        $result = $this->client->get('rates', ['base' => 'USD', 'quotes' => 'EUR']);

        $this->assertSame(0.9, $result['rate']);
    }

    public function testServesFromCacheWithoutHttpCall(): void
    {
        $this->cache->method('load')->willReturn('{"rate":1.5}');
        $this->curl->expects($this->never())->method('get');

        $this->assertSame(['rate' => 1.5], $this->client->get('rate/USD/CAD'));
    }

    public function testErrorStatusThrowsAndIsNotCached(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(422);
        $this->curl->method('getBody')->willReturn('{"status":422,"message":"invalid currency: XYZ"}');
        $this->cache->expects($this->never())->method('save');

        $this->expectException(ApiException::class);
        $this->client->get('rate/USD/XYZ');
    }

    public function testTransportFailureThrows(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('get')->willThrowException(new \Exception('timeout'));

        $this->expectException(ApiException::class);
        $this->client->get('currencies');
    }

    public function testNonJsonBodyThrows(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('<html>maintenance</html>');

        $this->expectException(ApiException::class);
        $this->client->get('currencies');
    }
}
