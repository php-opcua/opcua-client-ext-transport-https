---
eyebrow: 'Docs · Getting started'
lede:    'Install the package with Composer. Requires opcua-client v4.4 (the version that introduced the transport seam) and ext-curl.'

see_also:
  - { href: './quick-start.md',                meta: '4 min' }
  - { href: '../concepts/how-it-works.md',     meta: '5 min' }

prev: { label: 'Overview',    href: '../overview.md' }
next: { label: 'Quick start', href: './quick-start.md' }
---

# Installation

<!-- @code-block language="bash" label="terminal" -->
```bash
composer require php-opcua/opcua-client-ext-transport-https
```
<!-- @endcode-block -->

## Requirements

- **PHP** ≥ 8.2
- **`ext-curl`** — default HTTP backend
- **`ext-openssl`** — inherited from `opcua-client` for cert / crypto
- **`php-opcua/opcua-client`** ≥ 4.4 — adds `createProbe()` and
  `isSecureChannelExternal()` on `ClientTransportInterface`, plus the
  `openSecureChannelExternal()` branch that skips OPN when the transport
  provides TLS

## Verify

<!-- @code-block language="php" label="verify.php" -->
```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOpcua\Client\ExtTransportHttps\HttpsTransport;
use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Http\CurlHttpClient;

$transport = new HttpsTransport(
    httpClient: new CurlHttpClient(),
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://server.example:443/UA/',
);
echo $transport->isConnected() ? 'OK' : 'fail';
```
<!-- @endcode-block -->

## Running the integration suite

The integration tests target the `opcua-https-binary` service from the
`uanetstandard-test-suite` v1.5.0+ docker-compose stack:

<!-- @code-block language="bash" label="terminal" -->
```bash
git clone https://github.com/php-opcua/uanetstandard-test-suite.git
cd uanetstandard-test-suite
docker compose up -d opcua-https-binary
```
<!-- @endcode-block -->

The service listens on `https://localhost:4852/UA/TestServer` with a
pre-generated RSA 2048 certificate (CN `HttpsBinaryServer`, SAN
`localhost` + `127.0.0.1`).

<!-- @callout variant="note" title="HTTPS auth note" -->
UA-.NETStandard filters Anonymous out of HTTPS endpoint policies when
mTLS is off, so the integration test connects with Username /
Password (`admin` / `admin123` from the suite's seeded users). The
channel itself remains `SecurityPolicy::None` — TLS handles
confidentiality. Anonymous over HTTPS requires the server to be
configured with mTLS and the client to present a cert via
`CurlHttpClient(clientCertPath: ..., clientKeyPath: ...)`.
<!-- @endcallout -->

## Next

- [Quick start](./quick-start.md) — open a connection in ~15 lines.
- [How HTTPS transport works](../concepts/how-it-works.md) — wire format
  and the local fake HEL/ACK + OpenSecureChannel skip.
