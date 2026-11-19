<?php
declare(strict_types=1);

namespace Sidelah\CurrencyConverter\Api;

use Sidelah\CurrencyConverter\Model\HistoryRange;

interface ConfigInterface
{
    /**
     * Whether the converter page and rate endpoint are enabled for the current website.
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Configured "from" currency code; empty string means "use the store currency".
     *
     * @return string
     */
    public function getDefaultFrom(): string;

    /**
     * Configured "to" currency code.
     *
     * @return string
     */
    public function getDefaultTo(): string;

    /**
     * Initially selected chart range.
     *
     * @return HistoryRange
     */
    public function getDefaultRange(): HistoryRange;

    /**
     * Frankfurter API base URL, always with a trailing slash.
     *
     * @return string
     */
    public function getApiBaseUrl(): string;

    /**
     * HTTP timeout in seconds.
     *
     * @return int
     */
    public function getTimeout(): int;

    /**
     * Rate cache lifetime in seconds; 0 disables caching.
     *
     * @return int
     */
    public function getCacheLifetime(): int;
}
