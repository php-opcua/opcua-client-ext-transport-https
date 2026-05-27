<h1 align="center"><strong>OPC UA HTTPS Transport — PHP</strong></h1>

<div align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="assets/logo-light.svg">
    <img alt="OPC UA HTTPS Transport — PHP" src="assets/logo-light.svg" width="540">
  </picture>
</div>

<p align="center">
  <a href="https://github.com/php-opcua/opcua-client-ext-transport-https/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/php-opcua/opcua-client-ext-transport-https/tests.yml?branch=master&label=tests&style=flat-square" alt="Tests"></a>
  <a href="https://packagist.org/packages/php-opcua/opcua-client-ext-transport-https"><img src="https://img.shields.io/packagist/v/php-opcua/opcua-client-ext-transport-https?style=flat-square&label=packagist" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/php-opcua/opcua-client-ext-transport-https"><img src="https://img.shields.io/packagist/php-v/php-opcua/opcua-client-ext-transport-https?style=flat-square" alt="PHP Version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/php-opcua/opcua-client-ext-transport-https?style=flat-square" alt="License"></a>
</p>

<p align="center">
  <img src="https://custom-icon-badges.demolab.com/badge/Linux-✓-2ea44f?style=flat-square&logo=linux&logoColor=white" alt="Linux">
  <img src="https://custom-icon-badges.demolab.com/badge/macOS-✓-2ea44f?style=flat-square&logo=apple&logoColor=white" alt="macOS">
  <img src="https://custom-icon-badges.demolab.com/badge/Windows-✓-2ea44f?style=flat-square&logo=windows11&logoColor=white" alt="Windows">
</p>

---

`opc.https://` transport for [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client). Implements the HTTPS mappings of [OPC UA Part 6 §7.4](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4): each service request is a single HTTPS POST, TLS is the secure channel, the response body is the service response. Use it when the network forbids inbound `opc.tcp` but allows outbound `https`.

**What you get with v4.4.0:**

- **`HttpsTransport`** that drops straight into `ClientBuilder::setTransport()` — every OPC UA service call (`read`, `write`, `browse`, `subscribe`, `call`, `history*`) becomes one POST under the hood
- **`BinaryHttpsEncoding`** (Part 6 §7.4.4) — strips the UA-TCP framing into bare service requests, re-wraps responses for the core's decoder, fakes the HEL/ACK locally because `opc.https://` doesn't carry it on the wire
- **`CurlHttpClient`** with first-class TLS support — system CA store or pinned bundle, mutual TLS via PEM cert/key, HTTP keep-alive and TLS session resumption out of the box, full `extraCurlOptions` passthrough for proxies and corporate networks
- **Three PSR-14 events** (`HttpsRequestSent`, `HttpsResponseReceived`, `HttpsRequestFailed`) for tracing, metrics, audit
- **Five typed exceptions** rooted in `HttpsTransportException` (`HttpsRequestException`, `HttpsStatusException`, `EncodingException`, `UnsupportedEncodingException`) — route on the cause that matters
- **29 unit tests** running cross-platform (no Unix-only APIs), zero external dependency
- **Plug-and-play core integration** — the `opcua-client` v4.4 `ClientTransportInterface::createProbe()` and `isSecureChannelExternal()` seams mean the discovery probe runs through HTTPS too, and the OPC UA `OpenSecureChannel` exchange is short-circuited because TLS already wraps the channel

> **HTTPS auth note:** UA-.NETStandard filters Anonymous out of HTTPS endpoint policies when mTLS is off, so the integration suite connects with Username / Password (the channel itself stays `SecurityPolicy::None` — TLS provides confidentiality). For Anonymous over HTTPS, the server must be configured with mTLS and the client must supply `clientCertPath` + `clientKeyPath` on `CurlHttpClient`.

----

## Quick Start

```bash
composer require php-opcua/opcua-client-ext-transport-https
```

```php
use PhpOpcua\Client\ClientBuilder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Http\CurlHttpClient;
use PhpOpcua\Client\ExtTransportHttps\HttpsTransport;
use PhpOpcua\Client\Security\SecurityMode;
use PhpOpcua\Client\Security\SecurityPolicy;

$transport = new HttpsTransport(
    httpClient: new CurlHttpClient(verifyTls: true, caBundle: '/etc/ssl/certs/ca-bundle.crt'),
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://server.example:443/UA/',
);

$client = (new ClientBuilder())
    ->setSecurityPolicy(SecurityPolicy::None)
    ->setSecurityMode(SecurityMode::None)
    ->setTransport($transport)
    ->setUserCredentials('admin', 'admin123')
    ->connect('opc.https://server.example:443/UA/');

$value = $client->read('i=2259');
echo $value->getValue();

$client->disconnect();
```

## See It in Action

### Mutual TLS

```php
$transport = new HttpsTransport(
    httpClient: new CurlHttpClient(
        verifyTls: true,
        caBundle: '/etc/ssl/certs/server-ca-bundle.crt',
        clientCertPath: '/var/lib/myapp/client.pem',
        clientKeyPath: '/var/lib/myapp/client.key',
        clientKeyPassword: getenv('CLIENT_KEY_PASS') ?: null,
    ),
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://server.example:443/UA/',
);
```

### Behind a corporate proxy

```php
$http = new CurlHttpClient(
    verifyTls: true,
    extraCurlOptions: [
        CURLOPT_PROXY => 'http://proxy.corp.example:8080',
        CURLOPT_PROXYUSERPWD => 'user:secret',
    ],
);
```

`HTTPS_PROXY` / `NO_PROXY` from the environment are honoured automatically when no `CURLOPT_PROXY` is supplied.

### Observability via PSR-14

```php
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsRequestSent;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsResponseReceived;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsRequestFailed;

$transport = new HttpsTransport(
    httpClient: $http,
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://server.example:443/UA/',
    dispatcher: $yourPsr14Dispatcher,
);

class TraceListener {
    public function onSent(HttpsRequestSent $e): void { /* ... */ }
    public function onReceived(HttpsResponseReceived $e): void { /* ... */ }
    public function onFailed(HttpsRequestFailed $e): void {
        Log::warning("HTTPS {$e->statusCode} for {$e->url}: {$e->cause->getMessage()}");
    }
}
```

Zero overhead when no dispatcher is supplied — events are not constructed at all.

### Bring your own HTTP backend

```php
final class GuzzleHttpClient implements HttpClientInterface {
    public function __construct(private readonly \GuzzleHttp\Client $http) {}

    public function post(HttpRequest $r, float $timeout): HttpResponse {
        try {
            $resp = $this->http->post($r->url, [
                'body' => $r->body,
                'headers' => ['Content-Type' => $r->contentType, 'Accept' => $r->acceptHeader] + $r->extraHeaders,
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
            return new HttpResponse($resp->getStatusCode(), (string) $resp->getBody());
        } catch (\Throwable $e) {
            throw new HttpsRequestException($e->getMessage(), 0, $e);
        }
    }

    public function close(): void {}
}
```

## Why This Package?

- **Drops into the existing `Client`** — no new API surface for the application layer; the same `read`, `write`, `browse`, `call`, `subscribe`, `history*` methods you already use
- **`opcua-client` stays lean** — the core only adds two contract methods (`createProbe`, `isSecureChannelExternal`); HTTPS-specific logic lives here
- **PSR everywhere** — PSR-3 logger and PSR-14 dispatcher both optional; no global state
- **Cross-platform** — pure PHP + `ext-curl`; tested on Linux, macOS, Windows across PHP 8.2–8.5
- **No HTTP framework baked in** — `HttpClientInterface` is two methods; supply Guzzle, Symfony, PSR-18, or anything else
- **Three encodings, one transport** — Binary in v4.4, JSON / XML-SOAP in the roadmap, all sharing transport mechanics

## Documentation

The published site lives at <https://www.php-opcua.com/dev/components>. The Markdown sources ship under [`docs/`](docs/index.md):

| Section | Covers |
|---------|--------|
| **Getting started** — [Overview](docs/overview.md) · [Installation](docs/getting-started/installation.md) · [Quick start](docs/getting-started/quick-start.md) | What it is, how to install, first connection |
| **Concepts** — [How it works](docs/concepts/how-it-works.md) · [Encodings](docs/concepts/encodings.md) | Wire format, fake HEL/ACK, OPN skip, the three encodings of Part 6 §7.4 |
| **API** — [Transport](docs/api/transport.md) · [Encoding strategies](docs/api/encoding-strategies.md) · [HTTP client](docs/api/http-client.md) · [Events](docs/api/events.md) | Public surface |
| **Recipes** — [TLS and cert trust](docs/recipes/tls-and-cert-trust.md) · [Corporate proxy](docs/recipes/corporate-proxy.md) · [Connection pooling](docs/recipes/connection-pooling.md) | Operational patterns |
| **Reference** — [Exceptions](docs/reference/exceptions.md) | Every exception, cause, catch strategy |

## Testing

```bash
./vendor/bin/pest                                          # everything (29 unit + 1 integration)
./vendor/bin/pest tests/Unit/                              # 29 tests, no external deps
./vendor/bin/pest tests/Integration/ --group=integration   # E2E (requires uanetstandard-test-suite v1.5.0+)
```

## Ecosystem

| Package | Description |
|---------|-------------|
| [opcua-client](https://github.com/php-opcua/opcua-client) | Pure PHP OPC UA client (the core this extension hooks into) |
| [opcua-client-ext-reverse-connect](https://github.com/php-opcua/opcua-client-ext-reverse-connect) | Listener for OPC UA Reverse Connect (Part 6 §7.1.2.3) |
| [opcua-client-ext-pubsub](https://github.com/php-opcua/opcua-client-ext-pubsub) | OPC UA PubSub Subscriber — UDP + UADP + JSON |
| [opcua-cli](https://github.com/php-opcua/opcua-cli) | CLI tool — browse, read, write, watch, discover endpoints |
| [opcua-client-nodeset](https://github.com/php-opcua/opcua-client-nodeset) | Pre-generated PHP types from 51 OPC Foundation companion specifications |
| [uanetstandard-test-suite](https://github.com/php-opcua/uanetstandard-test-suite) | Docker-based OPC UA test servers (UA-.NETStandard) — v1.5.0+ ships the `opcua-https-binary` service |

## Community

Have questions, ideas, or want to share what you've built? Join the [GitHub Discussions](https://github.com/php-opcua/opcua-client/discussions).

## Contributing

Contributions welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Roadmap

See [ROADMAP.md](ROADMAP.md). All non-Binary encodings (JSON §7.4.5, XML SOAP §7.4.3, legacy SOAP/HTTP with WS-SecureConversation §7.3 + §6.6) are **community-driven** — no production server stack ships them end-to-end today, so they wait for a concrete use case before being scheduled.

## License

[MIT](LICENSE)
