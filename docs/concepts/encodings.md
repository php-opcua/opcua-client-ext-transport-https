---
eyebrow: 'Docs · Concepts'
lede:    'Three Part 6 §7.4 encodings (Binary, JSON, XML-SOAP) share one HTTPS transport orchestrator. Per-implementation status lives under /implementations.'

see_also:
  - { href: '../implementations/index.md',                                                  meta: '2 min' }
  - { href: '../api/encoding-strategies.md',                                                meta: '3 min' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4',                 meta: 'external', label: 'OPC UA Part 6 §7.4' }

prev: { label: 'How HTTPS transport works', href: './how-it-works.md' }
next: { label: 'Implementation status',     href: '../implementations/index.md' }
---

# Encodings

OPC UA Part 6 §7.4 defines three encodings that ride on the same HTTPS transport. The transport mechanics (one POST per call, TLS as the secure channel) are identical; the encodings differ only in how the service request and response bodies are serialised.

A fourth — and entirely separate — mapping, the legacy SOAP/HTTP with WS-SecureConversation from Part 6 §7.3 + §6.6, is **not** a §7.4 encoding: it has its own channel lifecycle and would need a different transport class. It is documented for completeness alongside the others.

| Encoding | Spec | Strategy class | Status (link) |
| --- | --- | --- | --- |
| Binary | §7.4.4 | `BinaryHttpsEncoding` | [Shipped](../implementations/binary.md) |
| JSON | §7.4.5 | `JsonHttpsEncoding` | [Foundation shipped](../implementations/json.md) |
| XML (SOAP body) | §7.4.3 | `XmlSoapHttpsEncoding` (planned) | [Roadmap](../implementations/xml-soap.md) |
| Legacy SOAP/HTTP + WS-SC | §7.3 + §6.6 | `WsSoapTransport` (planned, separate transport) | [Roadmap](../implementations/legacy-soap.md) |

## How the pluggability works

The strategy contract is five methods on {@see PhpOpcua\Client\ExtTransportHttps\Encoding\HttpsEncodingStrategy} — `contentType()`, `acceptHeader()`, `encodeRequest()`, `decodeResponse()`, `fakeAcknowledge()`. The `HttpsTransport` orchestrator delegates everything encoding-specific to the strategy and stays agnostic, so a future strategy (JSON, XML) drops in without touching the transport.

## Picking an encoding

Real-world: Binary is the default on every modern OPC UA stack (UA-.NETStandard, open62541, Unified Automation, Prosys, Softing, Kepware). Almost every server advertising "HTTPS" support means HTTPS Binary in practice. JSON appears primarily in PubSub (over MQTT, not HTTPS); XML is essentially absent from new deployments.

If you control the endpoint URL but not the server: pick `BinaryHttpsEncoding`. If the server advertises a JSON HTTPS endpoint specifically, see the [JSON status page](../implementations/json.md) for what's usable today versus what's roadmap.

## Per-encoding deep dives

For "what works today, what's missing, why" on each of the four mappings, see the dedicated [implementation status pages](../implementations/index.md). This page is the conceptual hub; that section is the operational ground truth.
