---
eyebrow: 'Docs · API'
lede:    'HttpClientInterface + CurlHttpClient — the POST-only HTTP backend the transport drives. Replace it to integrate any HTTP stack you prefer.'

see_also:
  - { href: './transport.md',                meta: '4 min' }
  - { href: '../recipes/tls-and-cert-trust.md', meta: '3 min' }
  - { href: '../recipes/corporate-proxy.md',    meta: '3 min' }

prev: { label: 'Encoding strategies', href: './encoding-strategies.md' }
next: { label: 'Events',              href: './events.md' }
---

# HTTP client

## `HttpClientInterface`

<!-- @code-block language="php" label="HttpClientInterface" -->
```php
namespace PhpOpcua\Client\ExtTransportHttps\Http;

interface HttpClientInterface
{
    public function post(HttpRequest $request, float $timeoutSeconds): HttpResponse;

    public function close(): void;
}
```
<!-- @endcode-block -->

Minimal on purpose — OPC UA Part 6 §7.4 uses POST exclusively. Throws
`HttpsRequestException` on network / TLS / connect failures (no
response). Non-2xx responses come back through `HttpResponse`; the
transport turns them into `HttpsStatusException`.

## `HttpRequest` / `HttpResponse`

<!-- @code-block language="php" label="DTOs" -->
```php
final readonly class HttpRequest
{
    public function __construct(
        public string $url,
        public string $body,
        public string $contentType,
        public string $acceptHeader,
        public array $extraHeaders = [],   // header-name => value
    );
}

final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers = [],        // lower-cased header-name => value
    );

    public function isSuccessful(): bool;  // 200..299
}
```
<!-- @endcode-block -->

## `CurlHttpClient`

Default backend, backed by `ext-curl`. Keeps a single cURL handle across
requests so HTTP keep-alive and TLS session resumption work out of the
box; the handle is released by `close()` or destruction.

<!-- @code-block language="php" label="CurlHttpClient constructor" -->
```php
public function __construct(
    bool $verifyTls = true,
    ?string $caBundle = null,
    ?string $clientCertPath = null,
    ?string $clientKeyPath = null,
    ?string $clientKeyPassword = null,
    array $extraCurlOptions = [],
);
```
<!-- @endcode-block -->

<!-- @params -->
<!-- @param name="verifyTls" type="bool" default="true" -->
When `true`, verify the server certificate chain and hostname. Disable
only in controlled test environments.
<!-- @endparam -->
<!-- @param name="caBundle" type="?string" default="null" -->
Optional CA bundle path. When `null`, cURL uses the system CA store.
<!-- @endparam -->
<!-- @param name="clientCertPath" type="?string" default="null" -->
Mutual TLS client certificate (PEM file).
<!-- @endparam -->
<!-- @param name="clientKeyPath" type="?string" default="null" -->
Mutual TLS private key (PEM file).
<!-- @endparam -->
<!-- @param name="clientKeyPassword" type="?string" default="null" -->
Optional password for the client private key.
<!-- @endparam -->
<!-- @param name="extraCurlOptions" type="array" default="[]" -->
`CURLOPT_*` array merged after the defaults. Use for proxy settings,
custom User-Agent, debug verbosity, etc.
<!-- @endparam -->
<!-- @endparams -->

### TLS defaults

The client uses cURL's own defaults: TLS 1.2+ negotiation, modern cipher
suites. To pin a specific TLS version, pass
`[CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3]` through
`$extraCurlOptions`.

## Bring your own client

Any class implementing `HttpClientInterface` works. A thin wrapper over
PSR-18 is roughly:

<!-- @code-block language="php" label="Psr18HttpClient (sketch)" -->
```php
use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Psr18HttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly Psr18Client $client,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
    ) {}

    public function post(HttpRequest $request, float $timeoutSeconds): HttpResponse
    {
        $r = $this->requests->createRequest('POST', $request->url)
            ->withHeader('Content-Type', $request->contentType)
            ->withHeader('Accept', $request->acceptHeader)
            ->withBody($this->streams->createStream($request->body));
        foreach ($request->extraHeaders as $n => $v) {
            $r = $r->withHeader($n, $v);
        }
        try {
            $r2 = $this->client->sendRequest($r);
        } catch (\Throwable $e) {
            throw new HttpsRequestException('PSR-18 request failed: ' . $e->getMessage(), 0, $e);
        }
        return new HttpResponse($r2->getStatusCode(), (string) $r2->getBody());
    }

    public function close(): void {}
}
```
<!-- @endcode-block -->

<!-- @callout variant="note" -->
A ready-made `Psr18HttpClient` may ship in a later minor — open an
issue if you want it sooner.
<!-- @endcallout -->
