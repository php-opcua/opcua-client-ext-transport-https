---
eyebrow: 'Docs · Recipes'
lede:    'Verify the server certificate, pin a CA bundle, enable mutual TLS, or disable verification (only in test).'

see_also:
  - { href: '../api/http-client.md',     meta: '3 min' }
  - { href: './corporate-proxy.md',      meta: '3 min' }

prev: { label: 'Events',         href: '../api/events.md' }
next: { label: 'Corporate proxy', href: './corporate-proxy.md' }
---

# TLS and certificate trust

TLS verification lives entirely on the HTTP client. The OPC UA
application certificate set via `ClientBuilder::setClientCertificate()`
is a separate concept and is *not* used by `CurlHttpClient`.

## Verify against the system CA store

<!-- @code-block language="php" label="default verify" -->
```php
new CurlHttpClient(verifyTls: true);
```
<!-- @endcode-block -->

## Pin a CA bundle

<!-- @code-block language="php" label="pinned CA" -->
```php
new CurlHttpClient(
    verifyTls: true,
    caBundle: '/etc/ssl/certs/internal-ca-bundle.crt',
);
```
<!-- @endcode-block -->

Useful when the server presents a certificate signed by an internal CA
not in the system store.

## Mutual TLS

<!-- @code-block language="php" label="mTLS" -->
```php
new CurlHttpClient(
    verifyTls: true,
    caBundle: '/etc/ssl/certs/server-ca-bundle.crt',
    clientCertPath: '/var/lib/myapp/client.pem',
    clientKeyPath: '/var/lib/myapp/client.key',
    clientKeyPassword: getenv('CLIENT_KEY_PASS') ?: null,
);
```
<!-- @endcode-block -->

The certificate and key are PEM. mTLS check happens during the TLS
handshake, before any UA frame leaves the client.

## Disable verification (test environments only)

<!-- @code-block language="php" label="dev only" -->
```php
new CurlHttpClient(verifyTls: false);
```
<!-- @endcode-block -->

<!-- @callout variant="warning" title="Never use in production" -->
Disables both peer chain validation and hostname matching. A network
attacker can interpose silently.
<!-- @endcallout -->

## Pin a TLS version

cURL negotiates TLS 1.2+ by default. To pin TLS 1.3 only:

<!-- @code-block language="php" label="TLS 1.3 only" -->
```php
new CurlHttpClient(
    verifyTls: true,
    extraCurlOptions: [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3],
);
```
<!-- @endcode-block -->

## Working with the test server

`uanetstandard-test-suite` v1.5.0+ ships an `opcua-https-binary` service
with a pre-generated RSA 2048 certificate (CN `HttpsBinaryServer`, SAN
includes `localhost` + `127.0.0.1`). For local tests:

<!-- @code-block language="php" label="test server (no verify)" -->
```php
new CurlHttpClient(verifyTls: false);    // dev only
```
<!-- @endcode-block -->

or, with verification:

<!-- @code-block language="php" label="test server (CA pinned)" -->
```php
new CurlHttpClient(
    verifyTls: true,
    caBundle: __DIR__ . '/../uanetstandard-test-suite/certs/ca/ca-cert.pem',
);
```
<!-- @endcode-block -->
