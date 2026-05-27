# OPC UA HTTPS Transport — Copilot Instructions

This repository contains `php-opcua/opcua-client-ext-transport-https`, an
`opc.https://` wire transport for [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)
v4.4.0+ — the version that introduced `ClientTransportInterface::createProbe()`
and `isSecureChannelExternal()`, plus the `openSecureChannelExternal()` branch
that skips OPN when the transport supplies TLS.

## Project context

Read first:

1. **[README.md](../README.md)** — value proposition, quick start, ecosystem
2. **[docs/overview.md](../docs/overview.md)** — what the package does and
   what it explicitly does not do
3. **[docs/concepts/how-it-works.md](../docs/concepts/how-it-works.md)** —
   wire format, fake HEL/ACK + OpenSecureChannel skip, lifecycle of
   `connect()`, security model
4. **[docs/implementations/](../docs/implementations/)** — per-mapping
   status: HTTPS Binary (shipped), HTTPS JSON (foundation), HTTPS XML
   SOAP (roadmap), legacy SOAP/HTTP + WS-SecureConversation (roadmap)
5. **[docs/api/](../docs/api/)** — exact constructor / method signatures
   for the public classes
6. **[ROADMAP.md](../ROADMAP.md)** — what is intentionally deferred and
   why each non-Binary encoding is community-driven

## Architecture

```
ClientBuilder::setTransport(HttpsTransport)
    │
    ▼
HttpsTransport implements ClientTransportInterface
    ├── createProbe() : self                          ← discovery probe
    └── isSecureChannelExternal() : true              ← skip OPN in core
        │
        ├── HttpsEncodingStrategy                     ← pluggable
        │     ├── BinaryHttpsEncoding (§7.4.4)         ← shipped
        │     ├── JsonHttpsEncoding   (§7.4.5)         ← foundation shipped
        │     │     └── ServiceCodecInterface registry
        │     │           └── GetEndpointsCodec
        │     ├── XmlSoapHttpsEncoding (§7.4.3)        ← roadmap
        │     └── (WsSoapTransport for §7.3 + §6.6)    ← roadmap (separate transport)
        │
        └── HttpClientInterface
              └── CurlHttpClient (ext-curl, keep-alive, TLS, mTLS)
```

## Key classes

- `src/HttpsTransport.php` — orchestrator implementing
  `ClientTransportInterface`. Intercepts the HEL frame for a synthetic
  ACK; for every other frame, asks the encoding strategy to translate
  to the HTTP body and POSTs via the HTTP client.
- `src/Encoding/HttpsEncodingStrategy.php` — five-method interface
  (`contentType`, `acceptHeader`, `encodeRequest`, `decodeResponse`,
  `fakeAcknowledge`).
- `src/Encoding/BinaryHttpsEncoding.php` — Part 6 §7.4.4. Content type
  `application/octet-stream`. Strips the 24-byte UA-TCP prefix on
  `encodeRequest`, rebuilds a synthetic frame on `decodeResponse`.
- `src/Encoding/JsonHttpsEncoding.php` — Part 6 §7.4.5 (v1.04 reversible
  mode). Content type `application/opcua+uajson`. Per-service
  binary↔JSON conversion via `ServiceCodecInterface` registry; only
  `GetEndpointsCodec` is registered in v4.4.0.
- `src/Encoding/Json/JsonEncoder.php` / `JsonDecoder.php` — five base
  type codecs (`NodeId`, `Variant`, `DataValue`, `StatusCode`,
  `DateTime`). Byte-exact with `Opc.Ua.JsonEncoder` 1.5.378.134
  reversible mode, validated against `tests/Fixtures/UaNetStandard/`.
- `src/Encoding/Json/Service/ServiceCodecInterface.php` — per-service
  binary↔JSON contract.
- `src/Encoding/Json/Service/GetEndpointsCodec.php` — first
  implementation (worked example for adding more).
- `src/Http/HttpClientInterface.php` — minimal POST-only contract.
- `src/Http/CurlHttpClient.php` — default backend (TLS, mTLS,
  keep-alive, proxy passthrough).
- `src/Event/` — three PSR-14 events (`HttpsRequestSent`,
  `HttpsResponseReceived`, `HttpsRequestFailed`).
- `src/Exception/` — five exception classes rooted in
  `HttpsTransportException`.

## Code conventions

- `declare(strict_types=1)` in every file
- Public readonly properties on every DTO and event (not getters)
- Full PHPDoc on every class and public method
  (`@param`, `@return`, `@throws`, `@see`)
- **No comments inside function bodies** — split into well-named
  methods instead
- Tests use Pest PHP (not PHPUnit)
- Integration tests are tagged with `->group('integration')` and
  require `uanetstandard-test-suite` v1.5.0+ running the
  `opcua-https-binary` service on `https://localhost:4852/UA/TestServer`
- Cross-platform code: no Unix domain sockets, no
  `stream_socket_pair(STREAM_PF_UNIX, …)` — the transport targets
  Linux, macOS, and Windows
- Coverage target: 99%+

## Dependencies

- `php-opcua/opcua-client` ^4.4 — the only hard dependency
- `psr/log` ^3.0, `psr/event-dispatcher` ^1.0 — interface-only,
  inherited from the core
- `ext-curl` — the default HTTP backend; a PSR-18 wrapper is
  user-supplied (sketch in `docs/api/http-client.md`)
- `ext-openssl` — inherited from the core for cert / crypto

## How the core is wired

- `ClientTransportInterface::createProbe(): self` in
  `php-opcua/opcua-client` is what lets the discovery probe ride on the
  same transport family as the main connection. `HttpsTransport`
  returns a fresh sibling sharing the same HTTP client, encoding, and
  endpoint URL.
- `ClientTransportInterface::isSecureChannelExternal(): bool` returns
  `true` for HTTPS — TLS already wraps the channel, so the core's
  `ManagesSecureChannelTrait::openSecureChannel()` short-circuits to
  `openSecureChannelExternal()`, initialising a `SessionService` with
  synthetic `secureChannelId` / `tokenId` and skipping the OPC UA
  OpenSecureChannel exchange entirely.
- `closeSecureChannel()` also early-returns under HTTPS — there is no
  UA-level `CloseSecureChannel` to send because there is no UA secure
  channel; TLS owns the channel close.
- `ManagesHandshakeTrait::performDiscoveryHandshake()` skips OPN on
  the probe side when the transport reports `isSecureChannelExternal()`.

## JSON HTTPS specifics

- The wire format is OPC UA Part 6 v1.04 reversible (object-form
  NodeId, `Type`/`Body` field names, `application/opcua+uajson`). v1.05
  Compact (`UaType`/`Value`, `application/json`) is a future refactor.
- Fixture validation: every change to the JSON codec must round-trip
  against the C# reference fixtures under `tests/Fixtures/UaNetStandard/`.
  Re-generate via the docker tool:
  ```bash
  docker run --rm -v $(pwd):/work \
      -w /work/tools/json-fixture-generator \
      mcr.microsoft.com/dotnet/sdk:8.0 \
      dotnet run -- /work/tests/Fixtures/UaNetStandard
  ```
- Adding a new service codec is mechanical: implement
  `ServiceCodecInterface`, register via `JsonHttpsEncoding::register()`,
  commit the paired binary+JSON fixture from the C# generator, and
  add a `Tests\Unit\Encoding\Json\Service\<Name>CodecTest` exercising
  both directions.

## Testing

- `vendor/bin/pest --exclude-group=integration` — unit suite, no
  external dependency
- `vendor/bin/pest --group=integration` — end-to-end against the
  `opcua-https-binary` service of
  [`uanetstandard-test-suite`](https://github.com/php-opcua/uanetstandard-test-suite)
  v1.5.0+
- Integration auth: UA-.NETStandard filters Anonymous out of HTTPS
  endpoint policies when `HttpsMutualTls = false`, so the integration
  test connects with the seeded `admin` / `admin123` user

The published docs site lives at
<https://www.php-opcua.com/dev/components>.
