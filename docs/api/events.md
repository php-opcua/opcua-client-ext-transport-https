---
eyebrow: 'Docs · API'
lede:    'Three PSR-14 events emitted around each HTTPS POST — observe traffic, time it, or audit failures without subclassing the transport.'

see_also:
  - { href: './transport.md',                meta: '4 min' }
  - { href: '../reference/exceptions.md',    meta: '3 min' }
  - { href: 'https://www.php-fig.org/psr/psr-14/', meta: 'external', label: 'PSR-14' }

prev: { label: 'HTTP client API', href: './http-client.md' }
next: { label: 'TLS and cert trust', href: '../recipes/tls-and-cert-trust.md' }
---

# Events

Pass a `Psr\EventDispatcher\EventDispatcherInterface` to the
`HttpsTransport` constructor and three events fire around each POST.
When the dispatcher argument is `null` nothing is constructed —
zero overhead.

All event classes live under
`PhpOpcua\Client\ExtTransportHttps\Event\`, are `final readonly`,
and carry only fields that are safe to log (no request body).

## `HttpsRequestSent`

<!-- @code-block language="php" label="HttpsRequestSent" -->
```php
final readonly class HttpsRequestSent
{
    public function __construct(
        public string $url,
        public string $contentType,
        public int $bodyLength,
    );
}
```
<!-- @endcode-block -->

Dispatched just before the POST hits the wire. Useful for tracing,
metrics, sampling.

## `HttpsResponseReceived`

<!-- @code-block language="php" label="HttpsResponseReceived" -->
```php
final readonly class HttpsResponseReceived
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public int $bodyLength,
    );
}
```
<!-- @endcode-block -->

Dispatched after a 2xx response, before the encoding strategy decodes
the body. Pair it with `HttpsRequestSent` for round-trip timing.

## `HttpsRequestFailed`

<!-- @code-block language="php" label="HttpsRequestFailed" -->
```php
final readonly class HttpsRequestFailed
{
    public function __construct(
        public string $url,
        public int $statusCode,         // 0 = network-level failure
        public Throwable $cause,        // the originating exception
    );
}
```
<!-- @endcode-block -->

Dispatched in two cases:

- **`statusCode === 0`** — the HTTP client raised
  `HttpsRequestException` (DNS, connect, TLS, read timeout). `cause`
  is the `HttpsRequestException` itself.
- **`statusCode > 0`** — the server returned a non-2xx. `cause` is
  the `HttpsStatusException` carrying `responseBody` for triage.

The transport re-throws after dispatching, so a handler can audit and
let the exception flow to the application code naturally.

## Ordering guarantees

For one `transport->send()`:

| Outcome | Events |
| --- | --- |
| 2xx | `HttpsRequestSent` → `HttpsResponseReceived` |
| Non-2xx | `HttpsRequestSent` → `HttpsRequestFailed` (statusCode > 0) |
| Network error | `HttpsRequestSent` → `HttpsRequestFailed` (statusCode = 0) |
| HEL frame (fake ACK locally) | no events — no HTTP traffic generated |

<!-- @callout variant="note" title="Dispatcher errors are swallowed" -->
If your dispatcher throws, the transport logs a `WARNING` and continues —
event delivery never breaks the OPC UA round-trip.
<!-- @endcallout -->
