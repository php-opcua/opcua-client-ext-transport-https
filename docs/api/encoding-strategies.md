---
eyebrow: 'Docs · API'
lede:    'HttpsEncodingStrategy + BinaryHttpsEncoding — encode UA service requests into HTTP bodies and back, plus the synthetic HEL/ACK exchange.'

see_also:
  - { href: './transport.md',                meta: '4 min' }
  - { href: '../concepts/encodings.md',      meta: '3 min' }
  - { href: '../reference/exceptions.md',    meta: '3 min' }

prev: { label: 'Transport API', href: './transport.md' }
next: { label: 'HTTP client API', href: './http-client.md' }
---

# Encoding strategies

## `HttpsEncodingStrategy`

<!-- @code-block language="php" label="HttpsEncodingStrategy interface" -->
```php
namespace PhpOpcua\Client\ExtTransportHttps\Encoding;

interface HttpsEncodingStrategy
{
    public function contentType(): string;
    public function acceptHeader(): string;
    public function encodeRequest(string $uaTcpFrame): string;
    public function decodeResponse(string $httpBody): string;
    public function fakeAcknowledge(string $helFrame): string;
}
```
<!-- @endcode-block -->

Each implementation owns:

- **MIME negotiation** via `contentType()` and `acceptHeader()`.
- **Request encoding** — input is whatever the core hands to
  `transport->send()` (a fully-framed UA-TCP message); output is the
  HTTP POST body.
- **Response decoding** — input is the HTTP body the server returned;
  output is a UA-TCP-shaped buffer the core's `BinaryDecoder` can read.
- **Synthetic ACK** — the OPC UA HTTPS mappings (Part 6 §7.4) do not
  carry HEL/ACK on the wire. The strategy builds one locally from the
  HEL frame the core emits.

## `BinaryHttpsEncoding` (Part 6 §7.4.4)

<!-- @code-block language="php" label="BinaryHttpsEncoding constructor" -->
```php
final class BinaryHttpsEncoding implements HttpsEncodingStrategy
{
    public const CONTENT_TYPE = 'application/octet-stream';

    public function __construct(
        int $negotiatedMaxMessageSize = 16 * 1024 * 1024,
        int $negotiatedMaxChunkCount = 0,
    );
}
```
<!-- @endcode-block -->

### Behaviour

| Method | Behaviour |
| --- | --- |
| `contentType()` / `acceptHeader()` | `application/octet-stream` (matches UA-.NETStandard's `HttpsTransportListener.kApplicationContentType`). |
| `encodeRequest($frame)` | Validates the 24-byte UA-TCP prefix (`MSG`/`CLO` + ChunkType + size + SecureChannelId + TokenId + SequenceNumber + RequestId), then returns everything after it — the bare service request body. |
| `decodeResponse($body)` | Wraps the bare service response in a synthetic UA-TCP frame: `MSGF` header + size + synthetic SecureChannelId (`1`) + TokenId (`1`) + SequenceNumber (`1`) + RequestId (`1`) + body. The core's `BinaryDecoder` reads it transparently because the external secure channel branch made channel/token IDs unused. |
| `fakeAcknowledge($helFrame)` | Decodes the HEL (via `HelloMessage::decode`) and produces an ACK whose `receiveBufferSize` / `sendBufferSize` echo the client's values and whose `maxMessageSize` / `maxChunkCount` come from the constructor arguments. |

### Error paths

All four methods raise `EncodingException` on malformed input:

| Input | Message prefix |
| --- | --- |
| Frame shorter than 24 bytes (`encodeRequest`) | `shorter than the expected 24-byte prefix` |
| Frame whose MessageType is not `MSG` or `CLO` | `expected MSG or CLO frame` |
| Empty HTTP body (`decodeResponse`) | `HTTPS response body is empty` |
| Wrong MessageType in HEL (`fakeAcknowledge`) | `Expected MessageType "HEL"` |
| Truncated HEL (`fakeAcknowledge`) | `Failed to read HEL frame header` / `Failed to decode HEL body` |

### What's `MSG` vs `CLO`

The core emits two MessageType values during normal operation:

- `MSG` — every service request after CreateSession (Read, Write, Call,
  Browse, History, …).
- `CLO` — emitted by `Client::disconnect()` to close the secure channel.
  HTTPS doesn't actually close a UA channel (there isn't one), but the
  encoding accepts the frame to keep the lifecycle symmetric.

## `JsonHttpsEncoding` (Part 6 §7.4.5)

<!-- @code-block language="php" label="JsonHttpsEncoding constructor" -->
```php
final class JsonHttpsEncoding implements HttpsEncodingStrategy
{
    public const CONTENT_TYPE = 'application/opcua+uajson';

    public function __construct(
        int $negotiatedMaxMessageSize = 16 * 1024 * 1024,
        int $negotiatedMaxChunkCount = 0,
        JsonEncoder $encoder = new JsonEncoder(),
        JsonDecoder $decoder = new JsonDecoder(),
    );

    public function register(ServiceCodecInterface $codec): void;
}
```
<!-- @endcode-block -->

Foundation shipped in v4.4.0 — see the [HTTPS JSON implementation status](../implementations/json.md) for the surface coverage and the [spec version note](../implementations/json.md#spec-version-note) for why the wire format follows v1.04 reversible mode.

### Behaviour

| Method | Behaviour |
| --- | --- |
| `contentType()` / `acceptHeader()` | `application/opcua+uajson` (Part 6 v1.04 §7.4.5). |
| `encodeRequest($frame)` | Strips the 24-byte UA-TCP prefix, reads the binary TypeId NodeId, dispatches to the registered {@see ServiceCodecInterface} matching that TypeId, and wraps the result in `{"TypeId": <NodeId>, "Body": <fields>}`. Raises `UnsupportedEncodingException` when no codec is registered for the TypeId. |
| `decodeResponse($body)` | Parses JSON, reads `TypeId.Id`, dispatches to the codec registered for that JSON TypeId, encodes the body as binary, wraps it in a synthetic UA-TCP frame the core decoder consumes. |
| `fakeAcknowledge($helFrame)` | Identical behaviour to `BinaryHttpsEncoding` — decodes HEL via `HelloMessage::decode` and produces an ACK locally. |
| `register($codec)` | Registers a {@see ServiceCodecInterface} for one OPC UA service. `GetEndpointsCodec` is registered in the constructor; additional codecs land via `register()`. |

### What's registered by default

| Service | Codec class | Status |
| --- | --- | --- |
| `GetEndpoints` | `GetEndpointsCodec` | Request side complete; response side handles `ResponseHeader` + empty `Endpoints` array |
| Everything else | none | raises `UnsupportedEncodingException` with a pointer to ROADMAP.md |

See the [HTTPS JSON status page](../implementations/json.md) for the full list of services that still need codecs and the recipe for adding one.

<!-- @callout variant="note" title="XML SOAP encoding" -->
`XmlSoapHttpsEncoding` (Part 6 §7.4.3) is on the community-driven
roadmap — see the [XML status page](../implementations/xml-soap.md).
<!-- @endcallout -->
