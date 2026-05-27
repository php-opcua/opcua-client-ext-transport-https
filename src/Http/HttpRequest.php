<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Http;

/**
 * Immutable HTTP request value object passed to {@see HttpClientInterface::post()}.
 *
 * Intentionally minimal: OPC UA HTTPS (Part 6 §7.4) only uses POST, and the
 * only headers the spec dictates are `Content-Type` and `Accept`.
 * Extra headers (e.g. `Authorization` for proxy auth, `User-Agent`) can be
 * supplied via the optional `extraHeaders` array.
 *
 * @psalm-immutable
 */
final readonly class HttpRequest
{
    /**
     * @param array<string, string> $extraHeaders Header name → value, applied
     *                                            verbatim on top of the
     *                                            Content-Type and Accept
     *                                            headers derived from the
     *                                            encoding strategy.
     */
    public function __construct(
        public string $url,
        public string $body,
        public string $contentType,
        public string $acceptHeader,
        public array $extraHeaders = [],
    ) {
    }
}
