<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Exception;

/**
 * Raised by encoding strategies that intentionally do not implement a given
 * Part 6 §7.4 message type — typically used by the JSON and XML-SOAP
 * strategies during incremental rollout, where the binary strategy is
 * complete but the others are still being filled in.
 */
class UnsupportedEncodingException extends HttpsTransportException
{
}
