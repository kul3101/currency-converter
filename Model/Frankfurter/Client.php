<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Model\Frankfurter;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Sidelah\CurrencyConverter\Api\ConfigInterface;
use Sidelah\CurrencyConverter\Api\FrankfurterClientInterface;
use Sidelah\CurrencyConverter\Model\Cache\Type as CacheType;
use Sidelah\CurrencyConverter\Model\Exception\ApiException;

class Client implements FrankfurterClientInterface
{
    private const USER_AGENT = 'Magento-Sidelah-CurrencyConverter/1.0';

    /**
     * @param CurlFactory $curlFactory
     * @param SerializerInterface $serializer
     * @param ConfigInterface $config
     * @param FrontendInterface $cache
     * @param StateInterface $cacheState
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly SerializerInterface $serializer,
        private readonly ConfigInterface $config,
        private readonly FrontendInterface $cache,
        private readonly StateInterface $cacheState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function get(string $path, array $query = [], ?int $cacheLifetime = null): array
    {
        $url = $this->config->getApiBaseUrl() . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $lifetime = $cacheLifetime ?? $this->config->getCacheLifetime();
        $useCache = $lifetime > 0 && $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER);
        $cacheKey = CacheType::TYPE_IDENTIFIER . '_' . hash('sha256', $url);

        if ($useCache) {
            $cached = $this->cache->load($cacheKey);
            $data = is_string($cached) ? $this->decode($cached) : null;
            if ($data !== null) {
                return $data;
            }
        }

        $body = $this->request($url);
        $data = $this->decode($body);
        if ($data === null) {
            $this->logger->error('Frankfurter API returned a non-JSON response.', ['url' => $url]);
            throw new ApiException(__('The exchange rate service returned an unexpected response.'));
        }

        if ($useCache) {
            $this->cache->save($body, $cacheKey, [CacheType::CACHE_TAG], $lifetime);
        }

        return $data;
    }

    /**
     * Execute the HTTP call and return the raw body of a 2xx response.
     *
     * @param string $url
     * @return string
     * @throws ApiException
     */
    private function request(string $url): string
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->config->getTimeout());
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('User-Agent', self::USER_AGENT);

        try {
            $curl->get($url);
        } catch (\Exception $e) {
            $this->logger->error('Frankfurter API request failed.', ['url' => $url, 'exception' => $e]);
            throw new ApiException(__('The exchange rate service is unavailable. Please try again later.'), $e);
        }

        $status = $curl->getStatus();
        $body = $curl->getBody();
        if ($status < 200 || $status >= 300) {
            $this->logger->warning('Frankfurter API returned an error.', [
                'url' => $url,
                'status' => $status,
                'message' => $this->decode($body)['message'] ?? null,
            ]);
            throw new ApiException(__('The exchange rate service could not process the request.'));
        }

        return $body;
    }

    /**
     * Decode a JSON body, returning null when it is not a JSON object or array.
     *
     * @param string $body
     * @return array<mixed>|null
     */
    private function decode(string $body): ?array
    {
        try {
            $data = $this->serializer->unserialize($body);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
