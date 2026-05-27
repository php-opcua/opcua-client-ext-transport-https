<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Http;

use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsRequestException;

/**
 * Minimal HTTP client contract used by the HTTPS transport.
 *
 * Deliberately small: only POST is needed (OPC UA Part 6 §7.4 uses POST
 * exclusively), and a single `close()` hook lets the transport release
 * connection-pooling resources when the OPC UA client disconnects.
 *
 * The package ships {@see CurlHttpClient} as the default implementation.
 * Applications can supply their own — for example wrapping a PSR-18 client
 * via {@see Psr18HttpClient}.
 */
interface HttpClientInterface
{
    /**
     * Issue an HTTPS POST.
     *
     * @param HttpRequest $request The pre-encoded request body and headers.
     * @param float $timeoutSeconds Hard upper bound on the round-trip.
     *
     * @throws HttpsRequestException If the request cannot be completed at
     *                               the network / TLS / connect layer (no
     *                               HTTP response available). Non-2xx
     *                               responses are returned normally so the
     *                               caller can decide how to react.
     */
    public function post(HttpRequest $request, float $timeoutSeconds): HttpResponse;

    /**
     * Release any pooled connections, cURL handles, or other resources.
     * Safe to call multiple times.
     */
    public function close(): void;
}
