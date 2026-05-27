---
eyebrow: 'Docs · Reference'
lede:    'Every exception the HTTPS transport can raise — base class plus four specialised subclasses for network, status, encoding, and unsupported-frame errors.'

see_also:
  - { href: '../api/transport.md',           meta: '4 min' }
  - { href: '../api/encoding-strategies.md', meta: '3 min' }

prev: { label: 'Connection pooling', href: '../recipes/connection-pooling.md' }
next: { label: 'No next page', href: '#' }
---

# Exceptions

All classes live under `PhpOpcua\Client\ExtTransportHttps\Exception\`
and extend the base `HttpsTransportException` (which itself extends
`\RuntimeException`).

<!-- @code-block language="text" label="Hierarchy" -->
```
\RuntimeException
└── HttpsTransportException                  (base)
    ├── HttpsRequestException                 network / TLS / connect failure
    ├── HttpsStatusException                  non-2xx HTTP response (carries body)
    ├── EncodingException                     strategy could not encode/decode
    └── UnsupportedEncodingException          strategy explicitly does not support a frame
```
<!-- @endcode-block -->

Catch `HttpsTransportException` for a single coarse-grained branch,
or the specific subclass when the application needs to react
differently (e.g. retry on `HttpsRequestException`, escalate on
`HttpsStatusException`).

## `HttpsTransportException`

The base class. Raised directly by `HttpsTransport` itself for cases
that don't fit the more specific subclasses — for example
`endpointUrl` with a wrong scheme, `receive()` called without a
pending response, `read()` after `close()`.

## `HttpsRequestException`

Raised by the HTTP client backend when the request cannot complete
at the network layer:

- DNS resolution failure
- TLS handshake refused
- Connection timeout
- Read timeout
- Connection reset

`CurlHttpClient` builds the message in the form
`cURL request to <url> failed: [<errno>] <error>`.

No `HttpResponse` is available — that's the whole point of the
exception. Pair with `HttpsRequestFailed` event (statusCode = 0).

## `HttpsStatusException`

Raised when the HTTP layer succeeded but the server returned a
non-2xx status. Carries the status code and the response body so the
caller can route on it (`401` → re-auth, `503` → backoff, …) or
quote the body in logs.

<!-- @code-block language="php" label="public state" -->
```php
public readonly int $statusCode;
public readonly string $responseBody;
```
<!-- @endcode-block -->

For HTTPS Binary, UA-.NETStandard's `HttpsTransportListener` returns:

- `400 Bad Request` for unsupported Content-Type or malformed buffer
- `401 Unauthorized` when mTLS is required but the client cert is
  missing or mismatched against `CreateSessionRequest.ClientCertificate`
- `405 Method Not Allowed` for non-POST requests
- `500 Internal Server Error` for unhandled exceptions during request processing
- `501 Not Implemented` when no callback is wired

## `EncodingException`

Raised by `HttpsEncodingStrategy::encodeRequest()` /
`decodeResponse()` / `fakeAcknowledge()` when the input is structurally
invalid:

- Frame shorter than the expected prefix (`encodeRequest`)
- MessageType not in the accepted set (`encodeRequest`)
- Empty HTTP response body (`decodeResponse`)
- Wrong or truncated HEL frame (`fakeAcknowledge`)

## `UnsupportedEncodingException`

Raised by strategies that *deliberately* do not implement a given
service message yet. Used during incremental rollout. In v4.4.0:

- `JsonHttpsEncoding` raises it when `encodeRequest()` receives a frame
  whose binary TypeId has no registered `ServiceCodecInterface` (the
  only codec registered by default is `GetEndpointsCodec`), or when
  `decodeResponse()` receives a JSON envelope with a `TypeId.Id` not in
  the registry. See [HTTPS JSON status](../implementations/json.md) for
  the recipe to register additional codecs.

<!-- @callout variant="note" -->
`BinaryHttpsEncoding` does not raise this exception — its support is
complete for the §7.4.4 surface.
<!-- @endcallout -->

## Catch hierarchy at a glance

| Application intent | Catch |
| --- | --- |
| Any transport failure | `HttpsTransportException` |
| Retry on network blips | `HttpsRequestException` |
| Route on HTTP status code | `HttpsStatusException` |
| Distinguish wire-format from policy failures | `EncodingException` vs `HttpsStatusException` |
| Detect missing strategy support | `UnsupportedEncodingException` |
