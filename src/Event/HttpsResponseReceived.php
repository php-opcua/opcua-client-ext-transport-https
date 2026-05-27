<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Event;

/**
 * Dispatched after the HTTPS transport receives a 2xx response and before
 * the encoding strategy decodes the body. Useful for timing, audit, and
 * sampling response sizes.
 */
final readonly class HttpsResponseReceived
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public int $bodyLength,
    ) {
    }
}
