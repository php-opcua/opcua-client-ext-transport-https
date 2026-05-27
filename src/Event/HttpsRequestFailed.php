<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Event;

use Throwable;

/**
 * Dispatched when an HTTPS POST fails — at either the network layer
 * (`HttpsRequestException`) or the HTTP-status layer
 * (`HttpsStatusException`).
 *
 * `statusCode` is `0` for network-level failures (no response was
 * received) and the HTTP status otherwise.
 */
final readonly class HttpsRequestFailed
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public Throwable $cause,
    ) {
    }
}
