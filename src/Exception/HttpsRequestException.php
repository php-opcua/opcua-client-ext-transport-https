<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Exception;

/**
 * Raised when the underlying HTTP client cannot complete the POST — network
 * failure, DNS error, TLS handshake refused, connect timeout, read timeout.
 *
 * Distinct from {@see HttpsStatusException}, which carries an HTTP response
 * but with a non-2xx status. This one means no usable response at all.
 */
class HttpsRequestException extends HttpsTransportException
{
}
