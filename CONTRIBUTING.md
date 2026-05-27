# Contributing to OPC UA HTTPS Transport (PHP)

## Welcome!

Thank you for considering contributing to this project! Every contribution
matters — bug reports, feature suggestions, documentation fixes, code
changes.

## What this package is

`php-opcua/opcua-client-ext-transport-https` is the `opc.https://` wire
transport for [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client)
v4.4+. It implements OPC UA Part 6 §7.4: each service call is a single
HTTPS POST, TLS is the secure channel, the response body is the service
response.

In v4.4 only the Binary encoding (§7.4.4) is shipped. JSON (§7.4.5) and
XML-SOAP (§7.4.3) are roadmapped — the encoding-strategy seam is already
in place.

## Development Setup

### Requirements

- PHP ≥ 8.2
- `ext-curl`, `ext-openssl`
- Composer
- A sibling checkout of [`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) — the `composer.json` declares a `path` repository at `../opcua-client`
- Docker (for the integration suite — the OPC UA HTTPS test server runs in a container)

### Installation

```bash
# In the parent directory:
git clone https://github.com/php-opcua/opcua-client.git
git clone https://github.com/php-opcua/opcua-client-ext-transport-https.git

cd opcua-client-ext-transport-https
composer install
```

### Test Server

The integration suite expects the `opcua-https-binary` service from
[`uanetstandard-test-suite`](https://github.com/php-opcua/uanetstandard-test-suite)
v1.5.0+ running on `https://localhost:4852/UA/TestServer`. That release
ships:

- A pre-generated RSA 2048 cert (CN `HttpsBinaryServer`, SAN
  `localhost` + `127.0.0.1`)
- `HttpsMutualTls = false` so plain TLS (no client cert) is accepted
  on `CreateSession`
- The `OPCFoundation.NetStandard.Opc.Ua.Bindings.Https` NuGet wired into
  the TestServer project

```bash
git clone https://github.com/php-opcua/uanetstandard-test-suite.git
cd uanetstandard-test-suite
docker compose up -d opcua-https-binary
```

## Running Tests

```bash
./vendor/bin/pest                                          # 55 unit + 1 integration
./vendor/bin/pest tests/Unit/                              # 55 tests
./vendor/bin/pest tests/Integration/ --group=integration   # E2E against opcua-https-binary
```

The unit suite uses pure-PHP streams APIs; no Unix-only calls.

### JSON wire-format fixtures

The `Tests\Unit\Encoding\Json\*` suite checks the PHP `JsonEncoder` byte-for-byte
against reference JSON emitted by UA-.NETStandard 1.5.378.134. The fixtures live
under `tests/Fixtures/UaNetStandard/` and ship with this repository — the unit
suite has zero external dependency. To regenerate them (e.g. after adding new
types), re-run the bundled C# generator in docker:

```bash
docker run --rm -v $(pwd):/work -w /work/tools/json-fixture-generator \
    mcr.microsoft.com/dotnet/sdk:8.0 \
    dotnet run -- /work/tests/Fixtures/UaNetStandard
```

## Project Structure

```
src/
├── HttpsTransport.php                  # Orchestrator implementing ClientTransportInterface
├── Encoding/
│   ├── HttpsEncodingStrategy.php       # Interface (5 methods)
│   └── BinaryHttpsEncoding.php         # Part 6 §7.4.4 implementation
├── Http/
│   ├── HttpClientInterface.php         # POST-only contract
│   ├── HttpRequest.php                 # Immutable readonly DTO
│   ├── HttpResponse.php                # Immutable readonly DTO
│   └── CurlHttpClient.php              # Default backend (ext-curl)
├── Event/                              # 3 PSR-14 events
│   ├── HttpsRequestSent.php
│   ├── HttpsResponseReceived.php
│   └── HttpsRequestFailed.php
└── Exception/                          # 5 typed exceptions
    ├── HttpsTransportException.php     # base
    ├── HttpsRequestException.php
    ├── HttpsStatusException.php
    ├── EncodingException.php
    └── UnsupportedEncodingException.php

tests/
├── Unit/
│   ├── HttpsTransportTest.php          # 14 tests
│   ├── Encoding/BinaryHttpsEncodingTest.php   # 11 tests
│   ├── Http/CurlHttpClientTest.php     # 4 tests
│   └── Helpers/InMemoryHttpClient.php  # Test fixture
└── Integration/
    └── BinaryHttpsE2ETest.php          # Skipped pending fase-1-missing.md

docs/                                   # Markdown source for the documentation site
```

## Design Principles

### Minimal core seam

The only contract additions in `opcua-client` v4.4 are
`ClientTransportInterface::createProbe()` and `isSecureChannelExternal()`.
HTTPS-specific logic stays in this package; the core never imports
anything from `PhpOpcua\Client\ExtTransportHttps\*`.

### POST-only HTTP

`HttpClientInterface` exposes one operation (`post`) plus `close`. OPC UA
Part 6 §7.4 uses POST exclusively — there is no GET / PUT / DELETE.

### TLS is the secure channel

`HttpsTransport::isSecureChannelExternal() === true`. The OPC UA
`OpenSecureChannel` exchange is short-circuited by the core via
`ManagesSecureChannelTrait::openSecureChannelExternal()`. Pin
`SecurityPolicy::None` + `SecurityMode::None` on the builder.

### Cross-platform compatibility

The package targets Linux, macOS, and Windows. No Unix-only APIs (no
`STREAM_PF_UNIX`, no `pcntl_*`, no `/proc`). The HTTP backend is
`ext-curl`, which ships with every PHP distribution.

### Readonly DTOs

`HttpRequest`, `HttpResponse`, and the three event classes are
`final readonly`. Access properties directly — no getters.

### Single-threaded transport

`HttpsTransport` owns mutable buffers (`pendingAck`, `pendingResponse`)
and is not thread-safe. One instance per OPC UA `Client`.

## Guidelines

### Code Style

```bash
composer format        # Apply formatting
composer format:check  # CI mode (fails on unformatted code)
```

Rules:

- `declare(strict_types=1)` everywhere
- Single quotes for strings
- Trailing commas in multiline arrays / args / params
- `not_operator_with_successor_space` (space after `!`)
- Ordered imports
- Type declarations for parameters, returns, properties
- `public readonly` properties on DTOs and events — no getters

### Documentation & Comments

- Every public class and method needs a PHPDoc block with `@param`,
  `@return`, `@throws`, and `@see` where applicable
- **No comments inside function bodies.** If a method needs an inline
  comment, split it into smaller well-named methods
- Update the relevant page in `docs/` for any feature change
- Update `CHANGELOG.md`
- Update `README.md` if the public API surface changes

### Testing

- Pest PHP syntax (not PHPUnit)
- Integration tests grouped with `->group('integration')`
- Cross-platform safe — `stream_socket_server` over loopback, not
  `stream_socket_pair(STREAM_PF_UNIX, …)`
- All four exception types must remain reachable from tests

### Commits

Prefix with `[ADD]`, `[UPD]`, `[PATCH]`, `[REF]`, `[DOC]`, `[TEST]`. The
CI workflow skips commits whose first line starts with `[DOC]`.

## Pull Request Process

1. Fork and create a feature branch
2. Write code + tests
3. `composer format`
4. Ensure unit tests pass; if you touch integration, document the test
   server setup
5. Update `docs/`, `README.md`, `CHANGELOG.md`
6. Submit the PR using the template

## Reporting Issues

Use the [issue templates](https://github.com/php-opcua/opcua-client-ext-transport-https/issues/new/choose):
**Bug**, **Feature**, **Question**. For bugs that affect the underlying
client (secure channel, session, services) open them on
[`opcua-client`](https://github.com/php-opcua/opcua-client/issues)
instead.
