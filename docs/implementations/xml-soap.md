---
eyebrow: 'Docs · Implementations'
lede:    'OPC UA HTTPS XML — SOAP body (Part 6 §7.4.3). Roadmap, community-driven. No code today; spec is defined but no production server stack implements it.'

see_also:
  - { href: '../../ROADMAP.md',                                                           meta: 'external', label: 'ROADMAP.md' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.3',             meta: 'external', label: 'OPC UA Part 6 §7.4.3' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/5.3',               meta: 'external', label: 'OPC UA Part 6 §5.3 (XML DataEncoding)' }

prev: { label: 'HTTPS JSON', href: './json.md' }
next: { label: 'Legacy SOAP/HTTP + WS-SecureConversation', href: './legacy-soap.md' }
---

# HTTPS XML — SOAP body (Part 6 §7.4.3)

<!-- @version-badge type="status" version="Roadmap — community-driven" -->

XML-encoded OPC UA service messages wrapped in a SOAP envelope, sent over HTTPS with `Content-Type: application/soap+xml`. Spec-complete but **no production server stack ships an interoperable implementation** as of 2026-05-27 — same situation as [HTTPS JSON](./json.md), with the additional weight that XML wire format is substantially heavier than Binary or JSON.

## Status

| Surface | Status |
| --- | --- |
| `XmlSoapHttpsEncoding` strategy class | No — not implemented |
| Base type XML codecs (analogous to `JsonEncoder` / `JsonDecoder`) | No — not implemented |
| SOAP envelope builder / parser | No — not implemented |
| SOAP-fault → `HttpsTransportException` subclass mapping | No — not implemented |
| Reference fixtures from a UA-.NETStandard analogue | No — UA-.NETStandard has no `Opc.Ua.XmlEncoder` wired into HTTPS either |

## Why not shipped

The same reasoning applies as for JSON, plus extras:

- **No server.** UA-.NETStandard `HttpsTransportListener` accepts `application/octet-stream` only. Eclipse Milo `transport-https` is binary-only. open62541 / node-opcua / asyncua have no HTTPS at all. No commercial vendor advertises HTTPS XML support.
- **Modern OPC UA deployments avoid XML.** XML is roughly 5–10× heavier on the wire than the equivalent Binary, parsers are slower, and most stacks moved away from it after 1.03. New deployments standardise on Binary; greenfield XML servers are essentially nonexistent.
- **Larger implementation cost than JSON.** Beyond the per-service binary↔XML mapping (same shape of work as JSON), there's the SOAP envelope itself — namespaces, `SOAPAction` HTTP header per service, fault-element parsing, and the XML-DataEncoding rules from Part 6 §5.3.

## What would need to land

If a real-world server emerges and a contributor picks this up, the same architectural pattern as JSON applies:

- [ ] `XmlSoapHttpsEncoding implements HttpsEncodingStrategy` — declares `Content-Type: application/soap+xml` (the exact MIME and `SOAPAction` header value per the spec).
- [ ] XML codecs for the base UA types — `NodeId`, `Variant`, `DataValue`, etc., following Part 6 §5.3.
- [ ] SOAP envelope builder (`<s:Envelope>` / `<s:Header>` / `<s:Body>`) and a minimal SAX or DOM parser for responses. The package will not pull in a full SOAP framework (see [`ROADMAP.md`](../../ROADMAP.md) — Won't do section).
- [ ] `XmlSoapServiceCodecInterface` mirroring `ServiceCodecInterface`, with per-service implementations.
- [ ] A way to test against a real server — patching UA-.NETStandard to wire its existing XML encoder into the HTTPS listener, building a Node/Python fixture, or asking the community for a server image.

## Open an issue first

If you need this, please open a GitHub issue with:

- the vendor + version of the server you target,
- whether you can capture a request/response sample on the wire,
- whether you can publicly share that sample as a test fixture.

This moves the encoding from "community-driven roadmap" to a concrete release plan. Don't open a PR before the issue — implementation without a server to validate against produces software nobody can use.

## See also

- [HTTPS JSON status](./json.md) — same shape of work; the JSON codec architecture is the template the XML codec would copy.
- [`ROADMAP.md`](../../ROADMAP.md) — full description of what's missing for all non-Binary encodings.
