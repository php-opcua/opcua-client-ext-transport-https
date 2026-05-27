---
eyebrow: 'Docs · Implementations'
lede:    'Status of the four HTTP-family transport mappings defined by OPC UA Part 6 — what ships, what is partial, what is roadmap.'

see_also:
  - { href: '../concepts/encodings.md',                                                meta: '3 min' }
  - { href: '../ROADMAP.md',                                                           meta: 'external', label: 'ROADMAP.md' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7',              meta: 'external', label: 'OPC UA Part 6 §7' }

prev: { label: 'Encodings',           href: '../concepts/encodings.md' }
next: { label: 'HTTPS Binary',        href: './binary.md' }
---

# Implementation status

This package targets four HTTP-family transport mappings defined by OPC UA Part 6. Each has its own page documenting **what works today**, **what's missing**, and **why** — so you can quickly tell whether the encoding you need is shippable, in progress, or a future contribution.

| Mapping | Spec | Strategy / Transport | Status |
| --- | --- | --- | --- |
| **HTTPS Binary** | [§7.4.4](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.4) | `BinaryHttpsEncoding` | **Shipped** — production-ready, E2E tested |
| **HTTPS JSON** | [§7.4.5](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.5) | `JsonHttpsEncoding` | **Foundation shipped** — base types + GetEndpoints; more services in ROADMAP |
| **HTTPS XML (SOAP body)** | [§7.4.3](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.3) | `XmlSoapHttpsEncoding` (planned) | **Roadmap** (community-driven) |
| **Legacy SOAP/HTTP + WS-SecureConversation** | [§7.3](https://reference.opcfoundation.org/Core/Part6/v105/docs/7.3) + [§6.6](https://reference.opcfoundation.org/Core/Part6/v105/docs/6.6) | `WsSoapTransport` (planned) | **Roadmap** (community-driven) |

## How to read this

- **Shipped** — pick this for production. Every documented surface is covered by unit tests and at least one E2E integration test against a real server.
- **Foundation shipped** — the architecture is in place and verified against a reference codec, but the binary↔JSON mapping covers a limited subset of OPC UA services. You can already test it byte-for-byte against UA-.NETStandard reference fixtures; you cannot yet connect to a live server end-to-end because no JSON HTTPS server exists in the open-source ecosystem.
- **Roadmap** (community-driven) — the strategy class does not exist yet. Open an issue with the server vendor/version you need to reach before opening a PR — see [`ROADMAP.md`](../../ROADMAP.md) for the precise list of missing pieces and the rationale for prioritising on demand.

## Pages

- [HTTPS Binary (§7.4.4)](./binary.md)
- [HTTPS JSON (§7.4.5)](./json.md)
- [HTTPS XML — SOAP body (§7.4.3)](./xml-soap.md)
- [Legacy SOAP/HTTP + WS-SecureConversation (§7.3 + §6.6)](./legacy-soap.md)
