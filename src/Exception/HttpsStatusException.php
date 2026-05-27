<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Exception;

/**
 * Raised when the server responds with an HTTP status outside the 2xx range.
 *
 * The status code and the (possibly empty) response body are exposed so
 * callers can route on 4xx vs 5xx, log the payload, or extract a SOAP fault
 * envelope from XML responses.
 */
class HttpsStatusException extends HttpsTransportException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $responseBody = '',
    ) {
        parent::__construct($message);
    }
}
