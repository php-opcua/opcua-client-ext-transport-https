---
eyebrow: 'Docs · Recipes'
lede:    'CurlHttpClient keeps one cURL handle alive across requests — HTTP keep-alive and TLS session resumption work out of the box.'

see_also:
  - { href: '../api/http-client.md',         meta: '3 min' }
  - { href: '../api/transport.md',           meta: '4 min' }

prev: { label: 'Corporate proxy', href: './corporate-proxy.md' }
next: { label: 'Exceptions',      href: '../reference/exceptions.md' }
---

# Connection pooling

OPC UA HTTPS sends one POST per service call. Without keep-alive, that
means a full TCP + TLS handshake every time — easily 200+ ms per call
on a typical link.

## Built-in behaviour

`CurlHttpClient` reuses a single `CurlHandle` for every `post()` call.
cURL persists the underlying TCP connection (HTTP keep-alive) and the
TLS session, so subsequent requests skip the handshake.

The handle is owned by the client instance and released by:

- explicit `$httpClient->close()`
- destructor (`__destruct`)
- `$transport->close()` (which delegates to `$httpClient->close()`)

<!-- @callout variant="tip" title="One transport per client" -->
Keep one `HttpsTransport` instance alive for as long as the OPC UA
`Client` is in use. Creating a new transport per call defeats
keep-alive and burns ~100 ms on every read.
<!-- @endcallout -->

## Multiple clients sharing one HTTP backend

The same `HttpClientInterface` instance can back several transports —
useful when an application connects to a fleet of servers and wants a
shared connection pool. The default cURL handle is single-threaded so
this is safe only when calls are sequential.

For concurrent calls against the same `HttpClientInterface`, supply a
dedicated `CurlHttpClient` per worker / thread / fiber.

## Tuning timeouts

`HttpsTransport(timeoutSeconds: …)` applies to both connect and read.
For long-running History reads, bump the budget:

<!-- @code-block language="php" label="bumped timeout" -->
```php
new HttpsTransport(
    httpClient: $httpClient,
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://srv:443/UA/',
    timeoutSeconds: 120.0,
);
```
<!-- @endcode-block -->

A separate per-request timeout is not exposed at the moment — open an
issue if you need it.
