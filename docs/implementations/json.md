---
eyebrow: 'Docs · Implementations'
lede:    'OPC UA HTTPS JSON (Part 6 §7.4.5). Foundation shipped — base type codecs + GetEndpoints + reference-fixture validation; service surface expands on community demand.'

see_also:
  - { href: '../api/encoding-strategies.md',                                              meta: '3 min' }
  - { href: '../../ROADMAP.md',                                                           meta: 'external', label: 'ROADMAP.md' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.5',             meta: 'external', label: 'OPC UA Part 6 §7.4.5' }

prev: { label: 'HTTPS Binary', href: './binary.md' }
next: { label: 'HTTPS XML — SOAP body', href: './xml-soap.md' }
---

# HTTPS JSON (Part 6 §7.4.5)

<!-- @version-badge type="status" version="Foundation shipped — v4.4.0" -->

JSON-encoded service messages over HTTPS. Architecturally identical to HTTPS Binary — one POST per service call, TLS as the secure channel — but the body is JSON rather than UA-TCP binary.

## What ships in v4.4.0

| Surface | Status |
| --- | --- |
| `JsonHttpsEncoding` strategy class (`Content-Type: application/opcua+uajson`) | Yes |
| Local `HEL` → `ACK` fake exchange | Yes — identical to `BinaryHttpsEncoding` |
| `Encoding\Json\JsonEncoder` / `JsonDecoder` for 5 base UA types (`NodeId`, `Variant`, `DataValue`, `StatusCode`, `DateTime`) | Yes — byte-exact with `Opc.Ua.JsonEncoder` 1.5.378.134 reversible mode |
| `ServiceCodecInterface` + per-service codec registry | Yes — `JsonHttpsEncoding::register(ServiceCodecInterface)` |
| `GetEndpointsCodec` — binary↔JSON round-trip for `GetEndpoints` service | Yes — request side complete; response side handles `ResponseHeader` + empty `Endpoints` array |
| 19 base-type reference fixtures + 4 GetEndpoints fixtures under `tests/Fixtures/UaNetStandard/` | Yes — emitted by `tools/json-fixture-generator/` (dotnet 8 console app, re-runnable in docker) |
| C# fixture generator using `Opc.Ua.JsonEncoder` from NuGet 1.5.378.134 | Yes |

Unknown service requests / responses raise `UnsupportedEncodingException` with a pointer to this page and to [`ROADMAP.md`](../../ROADMAP.md), so callers get an actionable error rather than a silent malformed POST.

## Spec version note

The HTTPS JSON mapping changed between OPC UA 1.04 and 1.05:

| Spec | Field names in JSON | Content-Type |
| --- | --- | --- |
| 1.04 reversible | `Type` / `Body`, NodeId as object `{"IdType","Id","Namespace"}` | `application/opcua+uajson` |
| 1.05 Compact / Verbose / RawData | `UaType` / `Value`, NodeId as string `"ns=2;i=1234"` | `application/json` |

This package implements the **1.04 reversible** wire format. Reason: that's what `Opc.Ua.JsonEncoder` 1.5.378.134 (the NuGet the rest of the `php-opcua` test-suite uses) produces, so the reference fixtures are byte-stable against a real toolchain. Adding 1.05 Compact support is a future refactor — the strategy is constructor-injectable to keep that door open.

## Why "foundation only"

The full service surface (~80 OPC UA service request/response types) would take weeks to implement and there is **no JSON HTTPS server in the open-source ecosystem to validate it against** (verified 2026-05-27 across UA-.NETStandard, Eclipse Milo, open62541, node-opcua, asyncua, and the major commercial vendors). Shipping spec-only code without a live server to round-trip against produces silently-buggy software — see the [community-driven roadmap](../../ROADMAP.md) for the full reasoning.

What the foundation gives you:

- A working `JsonHttpsEncoding` you can wire into `HttpsTransport` today.
- A proven byte-for-byte JSON codec for the 5 most common base types, validated against a reference implementation.
- An extensible `ServiceCodecInterface` so adding a new service is mechanical (~30–60 lines of PHP + one C# fixture pair).
- `GetEndpointsCodec` as the worked example to copy.

## What's NOT in v4.4.0

- [ ] Additional service codecs (`CreateSession`, `ActivateSession`, `CloseSession`, `Read`, …). Each follows the `ServiceCodecInterface` pattern — add a class, register via `JsonHttpsEncoding::register()`, commit the paired fixture from the C# generator.
- [ ] Additional base type codecs — `QualifiedName`, `LocalizedText`, `ExtensionObject` (the heaviest — TypeId + binary-or-XML body discriminator), `ByteString`, `Guid`, `DiagnosticInfo`, `ExpandedNodeId`. Each ~30–60 lines of encoder + decoder + fixture.
- [ ] Non-trivial `GetEndpointsResponse` bodies — currently `GetEndpointsCodec` asserts the `Endpoints` array is empty; real responses carry `EndpointDescription[]` with nested `ApplicationDescription`, `UserTokenPolicy[]`, etc.
- [ ] A JSON HTTPS server to integration-test against. Options (none free today):
    - Patch UA-.NETStandard's `HttpsTransportListener` to dispatch on Content-Type, wiring the existing `Opc.Ua.JsonEncoder` / `JsonDecoder` (~1–2 days of C# work; needs upstream PR or maintained fork).
    - Build a Node.js / Python fixture server from scratch — adds a second server stack to the test-suite.
- [ ] Support for OPC UA 1.05 Compact / Verbose JSON formats (different field names, different Content-Type — see the spec version note above).

## Extending it

Adding a new service codec is the lowest-friction path to expanding coverage:

<!-- @code-block language="php" label="adding ReadCodec" -->
```php
final class ReadCodec implements ServiceCodecInterface
{
    public function binaryRequestTypeId(): int { return 631; }   // ReadRequest_Encoding_DefaultBinary
    public function jsonRequestTypeId(): int   { return 629; }   // ReadRequest DataType
    public function binaryResponseTypeId(): int { return 634; }  // ReadResponse_Encoding_DefaultBinary
    public function jsonResponseTypeId(): int   { return 632; }  // ReadResponse DataType

    public function encodeRequestBody(BinaryDecoder $body): array { /* ... */ }
    public function decodeResponseBody(array $body): string       { /* ... */ }
}

// Wire it:
$encoding = new JsonHttpsEncoding();
$encoding->register(new ReadCodec());
```
<!-- @endcode-block -->

Add a paired binary+JSON fixture in the C# generator (`tools/json-fixture-generator/Program.cs`), run the dotnet container to emit the files, then commit them and add a `Tests\Unit\Encoding\Json\Service\ReadCodecTest` that exercises both directions.

## See also

- [`JsonHttpsEncoding` API reference](../api/encoding-strategies.md)
- [`ROADMAP.md`](../../ROADMAP.md) — full description of what's missing for end-to-end usability and why each piece is gated on community demand.
