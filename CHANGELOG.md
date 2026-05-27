# Changelog

## [v4.4.0] - TBD

- Requires `php-opcua/opcua-client` ^4.4 (the version that introduces `ClientTransportInterface::createProbe()` and `isSecureChannelExternal()`, plus the `openSecureChannelExternal()` branch that skips OPN when the transport supplies TLS as the secure channel)
- Requires `php-opcua/uanetstandard-test-suite` v1.5.0+ for the integration suite (new `opcua-https-binary` service on port 4852 backed by a pre-generated RSA 2048 certificate)

First public release. Ships the `opc.https://` transport as an optional extension of [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) — implementing the HTTPS mappings defined in OPC UA Part 6 §7.4. The core `opcua-client` only adds the two contract methods on `ClientTransportInterface`; everything else (transport, encoding strategies, HTTP client, events, exceptions) lives here under the `PhpOpcua\Client\ExtTransportHttps\*` namespace.

### Added — Transport

- **`HttpsTransport`** — implements `ClientTransportInterface`. Constructor: `(HttpClientInterface $httpClient, HttpsEncodingStrategy $encoding, string $endpointUrl, float $timeoutSeconds = 30.0, ?LoggerInterface $logger = null, ?EventDispatcherInterface $dispatcher = null)`. Accepts both `opc.https://` and `https://` URLs (the constructor normalises to `https://`). `connect()` is a no-op (HTTPS is stateless); `send()` intercepts `HEL` frames and primes a synthetic ACK locally without HTTP traffic, otherwise encodes via the strategy and POSTs. `receive()` returns the pending ACK or the re-framed response. `isSecureChannelExternal()` returns `true` — TLS is the secure channel, the core skips OpenSecureChannel.
- **`createProbe()`** — returns a fresh sibling `HttpsTransport` sharing the same HTTP client, encoding, and endpoint, so the core's discovery probe uses HTTPS instead of falling back to a hardcoded `TcpTransport`.

### Added — Encoding strategies

- **`HttpsEncodingStrategy`** interface — five methods (`contentType`, `acceptHeader`, `encodeRequest`, `decodeResponse`, `fakeAcknowledge`) that turn UA-TCP frames into HTTP bodies and back.
- **`BinaryHttpsEncoding`** (Part 6 §7.4.4) — content type `application/octet-stream` (matches UA-.NETStandard's `HttpsTransportListener.kApplicationContentType`). `encodeRequest()` validates the 24-byte UA-TCP prefix (MSG/CLO ChunkType, SecureChannelId, TokenId, SequenceHeader) and strips it; `decodeResponse()` rewraps the bare response in a synthetic UA-TCP frame with `secureChannelId=1`, `tokenId=1`, `sequenceNumber=1`, `requestId=1` (read-and-discarded by the core because the secure channel is external); `fakeAcknowledge()` decodes the client's HEL via `HelloMessage::decode` and produces a matching ACK whose buffer-size fields echo the client's values.
- **`JsonHttpsEncoding`** (Part 6 §7.4.5) — content type `application/opcua+uajson`. Working `fakeAcknowledge()`, `encodeRequest()`, `decodeResponse()` for any service whose codec is registered. Service-message binary↔JSON conversion delegates to {@see Encoding\Json\Service\ServiceCodecInterface} implementations registered via `JsonHttpsEncoding::register()`. Ships with {@see Encoding\Json\Service\GetEndpointsCodec} as the reference codec; the registry dispatches on the binary `TypeId` (request side) and the JSON `TypeId.Id` (response side). Requests for an unregistered service raise `UnsupportedEncodingException` with a pointer to `ROADMAP.md`.
- **`Encoding\Json\Service\ServiceCodecInterface`** + **`GetEndpointsCodec`** — first implementation. Maps `GetEndpointsRequest` binary body (TypeId 428 wire-side) to `{"TypeId":{"Id":426},"Body":{"RequestHeader":...,"EndpointUrl":...}}` and decodes `GetEndpointsResponse` JSON back into a synthetic UA-TCP frame the core decoder consumes. Validated against the `getendpoints_request_minimal.{bin.b64,json}` and `getendpoints_response_empty.{json,bin.b64}` fixture pairs.
- **`Encoding\Json\JsonEncoder` / `JsonDecoder`** — reversible-mode codecs for 5 base UA types (`NodeId`, `Variant`, `DataValue`, `StatusCode`, `DateTime`). Byte-exact with `Opc.Ua.JsonEncoder` from UA-.NETStandard 1.5.378.134, validated against 19 reference fixtures committed under `tests/Fixtures/UaNetStandard/` produced by `tools/json-fixture-generator/` (standalone dotnet 8 console app, re-runnable in docker via `mcr.microsoft.com/dotnet/sdk:8.0`).

### Added — HTTP client

- **`HttpClientInterface`** — minimal POST-only contract.
- **`HttpRequest` / `HttpResponse`** — immutable readonly DTOs.
- **`CurlHttpClient`** — default backend, backed by `ext-curl`. Keeps a single `CurlHandle` across requests so HTTP keep-alive and TLS session resumption work out of the box. Supports `verifyTls`, custom CA bundle, mutual TLS (client cert / key / key password), and a passthrough `$extraCurlOptions` array for proxies, custom User-Agent, etc.

### Added — Events (PSR-14)

Three event classes, all `final readonly`, dispatched only when an `EventDispatcherInterface` is supplied to the transport (zero overhead otherwise — events are not constructed at all):

- `HttpsRequestSent(url, contentType, bodyLength)` — before each POST.
- `HttpsResponseReceived(url, statusCode, bodyLength)` — after a 2xx response, before decoding.
- `HttpsRequestFailed(url, statusCode, cause)` — `statusCode=0` for network failures (`HttpsRequestException`), `statusCode=<actual>` for non-2xx (`HttpsStatusException`).

### Added — Exceptions

Five classes rooted in `\RuntimeException`:

- `HttpsTransportException` — base.
- `HttpsRequestException` — network / TLS / connect failure (no HTTP response).
- `HttpsStatusException` — non-2xx response, carries `statusCode` and `responseBody`.
- `EncodingException` — strategy could not encode or decode.
- `UnsupportedEncodingException` — strategy explicitly does not support a frame type yet.

### Added — Tests

- **59 unit tests** (`tests/Unit/`) — 11 `BinaryHttpsEncoding`, 14 `HttpsTransport`, 4 `CurlHttpClient`, 20 `Json/JsonCodecTest` (5 base types × encode-fixture-match + round-trip), 6 `JsonHttpsEncodingTest` (content type, ACK pass-through, unknown TypeId rejection, custom codec injection), 4 `Json/Service/GetEndpointsCodecTest` (binary→JSON envelope encoding, default-value omission, response decoding, end-to-end parseability). Cross-platform: zero Unix-only APIs.
- **Reference fixtures** under `tests/Fixtures/UaNetStandard/` — 19 JSON files emitted by `tools/json-fixture-generator/` using `Opc.Ua.JsonEncoder` from NuGet 1.5.378.134 (the same version `uanetstandard-test-suite` runs). Re-runnable via `docker run --rm -v $(pwd):/work -w /work/tools/json-fixture-generator mcr.microsoft.com/dotnet/sdk:8.0 dotnet run -- /work/tests/Fixtures/UaNetStandard`.
- **Helper `InMemoryHttpClient`** under `tests/Unit/Helpers/` — programmable HTTP backend for unit tests (queue of `HttpResponse` or exceptions, records every request).
- **Integration E2E** at `tests/Integration/BinaryHttpsE2ETest.php` — full round-trip against the `opcua-https-binary` service from `uanetstandard-test-suite` v1.5.0+: connects, reads `i=2259` (Server_ServerStatus_State), validates the returned `DataValue`, and disconnects.

### Authentication note

UA-.NETStandard's `HttpsServiceHost` filters the **Anonymous** user token policy out of the endpoint description whenever `HttpsMutualTls = false` (i.e. plain TLS, no client cert at the TLS layer). The HTTPS Binary integration therefore uses **Username/Password** identity (`admin` / `admin123` from the test-suite's seeded users); the channel itself remains `SecurityPolicy::None` because TLS provides confidentiality. The same flow works against any UA server that exposes a Username token policy on the HTTPS endpoint. Anonymous over HTTPS works only when the server is configured with mTLS — supply a client certificate via `CurlHttpClient(clientCertPath: ..., clientKeyPath: ...)` in that case.

### Roadmap

Full details in [`ROADMAP.md`](./ROADMAP.md). All items are **community-driven** — no concrete release plan until a real-world use case + a working test server emerge:

- **JSON encoding (Part 6 §7.4.5)** — the profile URI is registered by OPC Foundation but no production server stack implements it end-to-end (UA-.NETStandard rejects non-binary content types, Eclipse Milo's HTTPS module is incubating + binary-only, open62541 / node-opcua / asyncua have no HTTPS at all).
- **XML (SOAP body) encoding (Part 6 §7.4.3)** — same situation as JSON: spec-only, no server to validate against.
- **`https://` SOAP/XML legacy with WS-SecureConversation (Part 6 §7.3 + §6.6)** — legacy SOAP/HTTP for classic .NET 3.5 / WCF OPC UA servers; likely a separate `WsSoapTransport` because WS-SecureConversation needs per-request token plumbing outside TLS.
 
