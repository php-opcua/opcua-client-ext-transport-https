---
eyebrow: 'Docs · Implementations'
lede:    'Legacy https:// SOAP/HTTP with WS-SecureConversation (Part 6 §7.3 + §6.6). Roadmap, community-driven. Targets classic .NET 3.5 / WCF-era OPC UA servers; needs a separate transport class.'

see_also:
  - { href: '../../ROADMAP.md',                                                           meta: 'external', label: 'ROADMAP.md' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.3',               meta: 'external', label: 'OPC UA Part 6 §7.3' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/6.6',               meta: 'external', label: 'OPC UA Part 6 §6.6 (WS-SecureConversation)' }

prev: { label: 'HTTPS XML — SOAP body', href: './xml-soap.md' }
next: { label: 'No next page', href: '#' }
---

# Legacy SOAP/HTTP + WS-SecureConversation (Part 6 §7.3 + §6.6)

<!-- @version-badge type="status" version="Roadmap — community-driven" -->

The original OPC UA transport from the early days of the standard. **Distinct** from §7.4 — uses plain `https://` (or `http://`) with full SOAP/HTTP framing and message-level security negotiated via WS-SecureConversation tokens. Targets classic .NET 3.5 / WCF-era servers still deployed in long-running industrial sites.

## Status

| Surface | Status |
| --- | --- |
| `WsSoapTransport` class (a parallel transport to `HttpsTransport`, not a strategy) | No — not implemented |
| WS-Trust `RequestSecurityToken` / `RequestSecurityTokenResponse` flow | No — not implemented |
| WS-SecureConversation token rotation and renewal | No — not implemented |
| SOAP-fault → typed exception mapping | No — not implemented |
| Reference server image | No — none in the open-source ecosystem; UA-.NETStandard dropped the SOAP/HTTP binding years ago |

## Why a separate transport, not a strategy

The other three implementations ([Binary](./binary.md), [JSON](./json.md), [XML-SOAP](./xml-soap.md)) all share `HttpsTransport` as the orchestrator and differ only in the `HttpsEncodingStrategy`. WS-SecureConversation breaks that assumption: the channel lifecycle is fundamentally different.

- **TLS is not the secure channel.** WS-SecureConversation establishes a *message-level* security context outside the transport layer; TLS may or may not be involved.
- **Tokens flow per-request.** Every SOAP envelope carries a security context token that must be threaded through the orchestrator — the current `HttpsTransport` has no per-request token plumbing.
- **Two-phase channel open.** Before the first service request, a WS-Trust bootstrap exchange (`RequestSecurityToken` / `RequestSecurityTokenResponse`) negotiates the security context. That's a state machine the strategy pattern can't accommodate cleanly.

The realistic shape is a new top-level transport class — tentatively `WsSoapTransport implements ClientTransportInterface` — that owns its own session state and token lifecycle, while reusing whatever pieces of the existing transport can be factored out (the HTTP client backend, the events, the exception hierarchy).

## Why "community-driven"

- **Effectively legacy.** OPC UA SOAP/HTTP predates the modern Binary mapping by several years. New deployments do not use it. Existing deployments using it tend to be locked-in legacy systems that work and aren't being touched.
- **No accessible server.** UA-.NETStandard dropped its SOAP/HTTP binding well before the current 1.5.x line. open62541 never had it. node-opcua never had it. Realistic test infrastructure would mean either a third-party legacy vendor image (license-dependent, hard to share publicly) or building one from scratch in C# / Java for testing purposes.
- **Implementation cost is parallel to the binary transport.** WS-SecureConversation is a sizable protocol of its own — not "add another strategy" but "add another transport family". Estimate: weeks of focused work.

## What would need to land

If you need this — most likely because you're integrating a specific legacy OPC UA server that exposes only SOAP/HTTP — open a GitHub issue first with the vendor + version. The work breakdown:

- [ ] `WsSoapTransport implements ClientTransportInterface` — new class, mirrors what `HttpsTransport` does for §7.4 but with the WS-* envelope plumbing.
- [ ] WS-Trust bootstrap — issue `RequestSecurityToken` against the server's STS endpoint; parse `RequestSecurityTokenResponse` to extract the security context token, key material, lifetime.
- [ ] WS-SecureConversation envelope wrapper — thread the security context token into every outbound SOAP envelope, verify the incoming envelopes.
- [ ] Token rotation / renewal — handle the security context expiring mid-session.
- [ ] SOAP-fault subclass of `HttpsTransportException` carrying the SOAP fault code, reason, and any `wsse:*` detail.
- [ ] A server image — realistically a third-party legacy vendor image rather than a UA-.NETStandard fork, since upstream dropped support.

## Open an issue first

Same protocol as for the other community-driven encodings: GitHub issue with vendor + version + ideally a captured wire sample (sanitised). Don't open a PR without an upstream conversation — the work is large enough that scoping has to happen before any code is written.

## See also

- [HTTPS XML — SOAP body status](./xml-soap.md) — different protocol despite the surface similarity (both use SOAP); §7.4.3 uses `opc.https://` and rides on the modern HTTPS transport, this page covers the legacy §7.3 + §6.6 stack.
- [`ROADMAP.md`](../../ROADMAP.md) — full reasoning for the community-driven status.
