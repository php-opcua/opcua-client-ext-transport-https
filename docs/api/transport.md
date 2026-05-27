---
eyebrow: 'Docs · API'
lede:    'HttpsTransport — implements ClientTransportInterface for opc.https://, plugs into ClientBuilder::setTransport(), and orchestrates the HTTPS round-trips.'

see_also:
  - { href: './encoding-strategies.md',     meta: '3 min' }
  - { href: './http-client.md',             meta: '3 min' }
  - { href: '../concepts/how-it-works.md',  meta: '5 min' }

prev: { label: 'Encodings',     href: '../concepts/encodings.md' }
next: { label: 'Encoding strategies', href: './encoding-strategies.md' }
---

# `HttpsTransport`

Fully qualified name: `PhpOpcua\Client\ExtTransportHttps\HttpsTransport`.
Implements `PhpOpcua\Client\Transport\ClientTransportInterface`.

## Constructor

<!-- @method name="__construct(HttpClientInterface \$httpClient, HttpsEncodingStrategy \$encoding, string \$endpointUrl, float \$timeoutSeconds = 30.0, ?LoggerInterface \$logger = null, ?EventDispatcherInterface \$dispatcher = null)" returns="void" visibility="public" -->

<!-- @params -->
<!-- @param name="httpClient" type="HttpClientInterface" required -->
Backend that performs the POSTs. `CurlHttpClient` is shipped; PSR-18
wrappers are user-supplied.
<!-- @endparam -->
<!-- @param name="encoding" type="HttpsEncodingStrategy" required -->
The wire encoding. v4.4 ships `BinaryHttpsEncoding` (production-ready, see [HTTPS Binary status](../implementations/binary.md)) and `JsonHttpsEncoding` (foundation shipped, see [HTTPS JSON status](../implementations/json.md)). XML SOAP and legacy SOAP/HTTP are roadmap.
<!-- @endparam -->
<!-- @param name="endpointUrl" type="string" required -->
`opc.https://host:port/path` or `https://host:port/path`. The
constructor normalises `opc.https://` to `https://`. Invalid scheme
raises `HttpsTransportException`.
<!-- @endparam -->
<!-- @param name="timeoutSeconds" type="float" default="30.0" -->
Per-request hard upper bound passed to the HTTP client.
<!-- @endparam -->
<!-- @param name="logger" type="?LoggerInterface" default="null" -->
Optional PSR-3 logger. Defaults to `NullLogger`.
<!-- @endparam -->
<!-- @param name="dispatcher" type="?EventDispatcherInterface" default="null" -->
Optional PSR-14 dispatcher. When `null`, events are not emitted.
<!-- @endparam -->
<!-- @endparams -->

## Methods inherited from `ClientTransportInterface`

<!-- @method name="connect(string \$host, int \$port, ?float \$timeout = null): void" returns="void" visibility="public" -->
No-op — HTTPS is stateless. Throws `ConnectionException` if `close()`
was called.

<!-- @method name="send(string \$data): void" returns="void" visibility="public" -->
If the frame starts with `HEL`, asks the encoding for a fake ACK and
stores it; otherwise encodes the request via `encodeRequest()` and
POSTs.

<!-- @method name="receive(): string" returns="string" visibility="public" -->
Returns the pending fake ACK if any, then the decoded HTTP response
(re-wrapped in a synthetic UA-TCP frame).

<!-- @method name="setReceiveBufferSize(int \$size): void" returns="void" visibility="public" -->
Cap for the re-framed response size. Triggers `ProtocolException` on
`receive()` when exceeded.

<!-- @method name="close(): void" returns="void" visibility="public" -->
Releases the HTTP client. Idempotent.

<!-- @method name="isConnected(): bool" returns="bool" visibility="public" -->
`true` until `close()` is called.

<!-- @method name="createProbe(): self" returns="HttpsTransport" visibility="public" -->
Returns a fresh `HttpsTransport` sharing the same HTTP client /
encoding / endpoint, for use as the discovery probe.

<!-- @method name="isSecureChannelExternal(): bool" returns="bool" visibility="public" -->
Always `true` — TLS is the secure channel, the core skips
OpenSecureChannel.

## Events dispatched

When a dispatcher is supplied:

- **`HttpsRequestSent`** — before the POST (`url`, `contentType`,
  `bodyLength`).
- **`HttpsResponseReceived`** — after a 2xx response, before decoding
  (`url`, `statusCode`, `bodyLength`).
- **`HttpsRequestFailed`** — on network failure (`statusCode=0`) or
  non-2xx (`statusCode=<actual>`), with the originating exception in
  `cause`.

## Logging

- `INFO` — none in the default path (HTTPS is too verbose; emit at
  application level if needed).
- `WARNING` — non-2xx response; event-dispatcher throw caught.
- `ERROR` — `HttpsRequestException` from the HTTP backend.

<!-- @callout variant="warning" title="Not thread-safe" -->
The transport owns mutable buffers (`pendingAck`, `pendingResponse`);
use one instance per `Client`.
<!-- @endcallout -->
