<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Http;

use CurlHandle;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsRequestException;

/**
 * Default {@see HttpClientInterface} implementation, backed by ext-curl.
 *
 * Keeps a single cURL handle across requests so HTTP keep-alive and TLS
 * session resumption work out of the box; the handle is released by
 * {@see close()} (or by destruction).
 */
final class CurlHttpClient implements HttpClientInterface
{
    private ?CurlHandle $handle = null;

    /**
     * @param bool $verifyTls Whether to verify the server certificate chain
     *                        and hostname. Disable only for controlled test
     *                        environments — see {@see SECURITY.md}.
     * @param ?string $caBundle Optional CA bundle path. When `null`, cURL
     *                         uses the system CA store.
     * @param ?string $clientCertPath Optional client certificate (PEM) for
     *                                mutual TLS.
     * @param ?string $clientKeyPath Optional client private key (PEM).
     * @param ?string $clientKeyPassword Optional password for the client
     *                                   private key.
     * @param array<int, mixed> $extraCurlOptions Additional `CURLOPT_*`
     *                                            options applied verbatim
     *                                            after the defaults.
     */
    public function __construct(
        private readonly bool $verifyTls = true,
        private readonly ?string $caBundle = null,
        private readonly ?string $clientCertPath = null,
        private readonly ?string $clientKeyPath = null,
        private readonly ?string $clientKeyPassword = null,
        private readonly array $extraCurlOptions = [],
    ) {
    }

    public function post(HttpRequest $request, float $timeoutSeconds): HttpResponse
    {
        $ch = $this->handle ??= curl_init();

        $headerLines = [
            'Content-Type: ' . $request->contentType,
            'Accept: ' . $request->acceptHeader,
        ];
        foreach ($request->extraHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        curl_reset($ch);
        curl_setopt_array($ch, [
            CURLOPT_URL => $request->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request->body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ($timeoutSeconds * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($timeoutSeconds * 1000),
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $colon = strpos($line, ':');
                if ($colon !== false) {
                    $name = strtolower(trim(substr($line, 0, $colon)));
                    $responseHeaders[$name] = trim(substr($line, $colon + 1));
                }

                return strlen($line);
            },
        ]);

        if ($this->caBundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caBundle);
        }
        if ($this->clientCertPath !== null) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->clientCertPath);
        }
        if ($this->clientKeyPath !== null) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->clientKeyPath);
        }
        if ($this->clientKeyPassword !== null) {
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->clientKeyPassword);
        }
        foreach ($this->extraCurlOptions as $option => $value) {
            curl_setopt($ch, $option, $value);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);

            throw new HttpsRequestException("cURL request to {$request->url} failed: [{$errno}] {$error}");
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($statusCode, (string) $body, $responseHeaders);
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
