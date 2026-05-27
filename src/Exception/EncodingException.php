<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Exception;

/**
 * Raised by an {@see \PhpOpcua\Client\ExtTransportHttps\Encoding\HttpsEncodingStrategy}
 * when a request body cannot be produced or a response body cannot be parsed.
 *
 * Distinct from {@see UnsupportedEncodingException}, which signals that the
 * strategy itself does not yet implement a given message type.
 */
class EncodingException extends HttpsTransportException
{
}
