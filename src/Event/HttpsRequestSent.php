<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Event;

/**
 * Dispatched immediately before the HTTPS transport issues a POST. Carries
 * the target URL, the negotiated Content-Type, and the size of the encoded
 * body — not the body itself, to keep event payloads cheap and avoid
 * leaking secrets in audit logs.
 */
final readonly class HttpsRequestSent
{
    public function __construct(
        public string $url,
        public string $contentType,
        public int $bodyLength,
    ) {
    }
}
