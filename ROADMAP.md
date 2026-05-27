# Roadmap

## Future work — community-driven

### JSON encoding (Part 6 §7.4.5) — foundation shipped, service mapping pending

The [HTTPS JSON mapping](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.5) sends the OPC UA service request and response as a JSON document in the HTTP body, with `Content-Type: application/opcua+uajson`.

**Shipped in v4.4.0:**

- `JsonHttpsEncoding` strategy class — implements `HttpsEncodingStrategy`, declares `application/opcua+uajson`, working `fakeAcknowledge()` (HEL→ACK pass-through). `encodeRequest()` / `decodeResponse()` dispatch to a per-service codec registry.
- `JsonEncoder` / `JsonDecoder` for 5 base UA types (`NodeId`, `Variant`, `DataValue`, `StatusCode`, `DateTime`) under `src/Encoding/Json/`. Reversible mode. **Byte-exact** with `Opc.Ua.JsonEncoder` from UA-.NETStandard 1.5.378.134, validated against 19 reference fixtures.
- `ServiceCodecInterface` + `GetEndpointsCodec` — first registered service. Decodes binary `GetEndpointsRequest` to the JSON envelope and re-encodes `GetEndpointsResponse` JSON to the binary frame the core consumes. Validated against `getendpoints_request_minimal.{bin.b64,json}` and `getendpoints_response_empty.{json,bin.b64}` fixture pairs.
- C# fixture generator under `tools/json-fixture-generator/` — emits both base-type `.json` and paired service-message `.bin.b64`+`.json` fixtures. Re-runnable in docker via `mcr.microsoft.com/dotnet/sdk:8.0`.

**What still needs to land for end-to-end usability:**

- [ ] **Additional service codecs.** Only `GetEndpointsCodec` ships in v4.4.0. The connect pipeline also needs `CreateSessionCodec`, `ActivateSessionCodec`, `CloseSessionCodec`, plus one operational codec like `ReadCodec` for a non-trivial round-trip. Each follows the same `ServiceCodecInterface` pattern — add a class, register via `JsonHttpsEncoding::register()`, commit the paired fixture from the C# generator. Estimate: ~80 OPC UA service request/response types total; the most commonly-used 5–10 cover ~95% of real-world traffic.
- [ ] **Additional base type codecs.** The 5 shipped (NodeId, Variant, DataValue, StatusCode, DateTime) cover the most common bodies, but full service requests/responses also need `QualifiedName`, `LocalizedText`, `ExtensionObject` (the heaviest one — type-id + binary-or-XML body discriminator), `ByteString`, `Guid`, `DiagnosticInfo`, `ExpandedNodeId`. Each ~30–60 lines of encoder + decoder + fixture.
- [ ] **Non-trivial response bodies.** `GetEndpointsCodec` currently asserts the `Endpoints` array is empty when decoding (the `getendpoints_response_empty` fixture). Real responses contain `EndpointDescription[]` with nested `ApplicationDescription`, `UserTokenPolicy[]`, `ServerCertificate` ByteString, etc. — needs the additional base-type codecs above plus the array marshalling.
- [ ] **Server fixture.** No JSON HTTPS server exists in the open-source ecosystem (verified 2026-05-27 across UA-.NETStandard, Eclipse Milo, open62541, node-opcua, asyncua, and major commercial vendors). Options:
  - Patch UA-.NETStandard's `HttpsTransportListener` to dispatch on Content-Type (~1–2 days C# work; needs upstream PR or fork).
  - Build a Node.js / Python fixture server from scratch — adds a second server stack to `uanetstandard-test-suite` and breaks its "UA-.NETStandard only" positioning.
- [ ] **Integration test.** Once a server exists, mirror `BinaryHttpsE2ETest` against the JSON endpoint. Currently nothing to point at — the wire-format correctness is guaranteed by the binary↔JSON round-trip against the C# reference fixtures, but no live server round-trip has been performed.

**Why this is shipped as a foundation rather than fully implemented:**

| Stack | HTTPS JSON server-side |
|---|---|
| UA-.NETStandard | Profile URI defined as a constant; `HttpsTransportListener` rejects anything other than `application/octet-stream`. |
| Eclipse Milo | `transport-https` module is *incubating* and CLIENT-only; the codec hardcodes `application/octet-stream` and the JSON branch throws `// TODO`. |
| open62541 | No HTTPS at all — only `opc.tcp://`. |
| node-opcua / asyncua | No HTTPS at all — only `opc.tcp://`. |
| Commercial vendors (Unified Automation, Prosys, Softing, Kepware) | "HTTPS" in marketing copy means HTTPS Binary; no vendor publishes JSON HTTPS support. |

The industry has effectively moved past Part 6 §7.4.5 — the modern "JSON OPC UA" deployment story is **PubSub JSON over MQTT** ([`php-opcua/opcua-client-ext-pubsub`](https://github.com/php-opcua/opcua-client-ext-pubsub) covers the subscriber side), not HTTPS client-server. Implementing the full service-message mapping (~80 service types) without a server to test it end-to-end is not a useful investment until a real-world use case emerges. The foundation that *is* shipped (base codecs + strategy skeleton + fixture pipeline) is the part that lets a future contributor pick this up incrementally.

Open an issue with the server vendor / version you need to reach if this matters to you — it moves JSON from "community-driven foundation" to a concrete release plan.

### XML (SOAP body) encoding (Part 6 §7.4.3)

[OPC UA Part 6 §7.4.3](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.3) defines the XML encoding for the modern `opc.https://` mapping. The HTTP body is a SOAP envelope wrapping the OPC UA XML-encoded service request/response; `Content-Type: application/soap+xml`. Same `HttpsTransport`, swap the strategy.

Tracked **on community request only** — same situation as JSON above. Modern OPC UA deployments overwhelmingly use Binary; XML is heavier on the wire, has the same "spec-only, no server" problem, and is rarely chosen for new integrations. Open an issue if you have a server that requires it.

- [ ] `XmlSoapHttpsEncoding` — implement the strategy interface against the §7.4.3 XML format.
- [ ] Base type XML codecs — analogous to the JSON ones already shipped, but with the XML wire format from §5.3.
- [ ] Service-message binary↔XML mapping — same scope as the JSON service mapping above.
- [ ] Server fixture: identify a UA server that actually exposes a §7.4.3 HTTPS XML endpoint (in practice, the same gap as JSON — needs verification per stack).
- [ ] Doc page covering the encoding-specific quirks (SOAP envelope structure, XML namespace handling, performance characteristics vs Binary).

### `https://` SOAP/XML legacy with WS-SecureConversation (Part 6 §7.3 + §6.6)

[Part 6 §7.3](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.3) defines the legacy SOAP/HTTP mapping that predates `opc.https://`: plain `https://` (or `http://`) with full SOAP/HTTP framing, and [Part 6 §6.6](https://reference.opcfoundation.org/Core/Part6/v105/docs/6.6) defines the WS-SecureConversation message-level security used to negotiate a security context outside the TLS layer. Targets integration with classic .NET 3.5 / WCF-era OPC UA servers still deployed in long-running industrial sites.

Tracked **on community request only**. The implementation is substantially larger than the other roadmap items and effectively a parallel transport stack — open an issue first with the server vendor / version you need to reach.

- [ ] Likely a separate `WsSoapTransport` class (not a strategy under `HttpsTransport`). The channel lifecycle is fundamentally different: WS-SecureConversation establishes a security context outside TLS and threads it through every SOAP envelope, so the orchestrator needs per-request token plumbing the existing `HttpsTransport` does not have.
- [ ] WS-Trust `RequestSecurityToken` / `RequestSecurityTokenResponse` flow to bootstrap the security context.
- [ ] WS-SecureConversation token rotation and renewal across long-lived sessions.
- [ ] SOAP-faults → `HttpsTransportException` subclass that carries the SOAP fault code and reason.
- [ ] Server fixture: realistically a third-party legacy server in a docker image rather than UA-.NETStandard, which dropped the SOAP/HTTP binding years ago.

---

## Won't do (by design)

### Bundle a SOAP framework in-tree

The XML and legacy SOAP work, if and when they ship, will rely on the existing `ext-curl` backend plus a minimal XML/SOAP envelope builder. **No** `php-soap/*`, `nelmio/*`, or full SOAP framework dependency. The package stays small, dependency-free at runtime beyond `ext-curl`, and cross-platform. Anything heavier belongs in a downstream package.

### Modify the core `opcua-client` public surface

The two seams added in core v4.4 (`ClientTransportInterface::createProbe()` and `isSecureChannelExternal()`, plus the `openSecureChannelExternal()` branch in `ManagesSecureChannelTrait`) are intentionally minimal. Future encoding work builds entirely on those — if a future encoding genuinely needs a new core seam, that change is discussed on the core repository first and lands there before any work here depends on it. This package stays strictly additive.

### Replace `ext-curl` with a Fiber-based HTTP client

`HttpClientInterface` is a two-method contract. If async I/O is required (e.g. running inside an Amp or ReactPHP loop), supply your own implementation. The default `CurlHttpClient` will not gain a Fiber-aware mode — keeping it simple, blocking, and cross-platform is a deliberate trade-off.
