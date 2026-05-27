<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Http;

/**
 * Immutable HTTP response value object returned by {@see HttpClientInterface::post()}.
 *
 * @psalm-immutable
 */
final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers Lower-cased header name → value
     *                                       (last value wins for duplicates).
     */
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
