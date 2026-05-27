---
eyebrow: 'Docs · Overview'
lede:    'HTTPS transport for OPC UA (Part 6 §7.4). Speaks opc.https:// to a server via plain POST + TLS, with pluggable Binary / JSON / XML-SOAP encodings.'

see_also:
  - { href: './getting-started/installation.md',  meta: '2 min' }
  - { href: './concepts/how-it-works.md',         meta: '5 min' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4', meta: 'external', label: 'OPC UA Part 6 §7.4' }

prev: { label: 'No previous page', href: '#' }
next: { label: 'Installation',     href: './getting-started/installation.md' }
---

# Overview

`php-opcua/opcua-client-ext-transport-https` is a wire transport for
[`php-opcua/opcua-client`](https://github.com/php-opcua/opcua-client) that
talks `opc.https://` instead of `opc.tcp://`. It implements the HTTPS
mappings from OPC UA Part 6 §7.4 — TLS is the secure channel, each UA
service request is a single HTTP POST, and the response body is the
service response.

## When to use it

Reach for HTTPS when the network forbids inbound `opc.tcp` but allows
outbound `https`: cloud-to-edge gateways, corporate proxies, restricted
firewall zones. HTTPS keeps the OPC UA protocol logic intact; only the
wire layer changes.

## What ships in v4.4.0

| Component | Status |
| --- | --- |
| `HttpsTransport` (orchestrator implementing `ClientTransportInterface`) | Yes |
| `BinaryHttpsEncoding` (Part 6 §7.4.4) | Yes — strips UA-TCP framing into bare service requests |
| `CurlHttpClient` (default `HttpClientInterface` backend) | Yes — TLS, mTLS, keep-alive |
| 3 PSR-14 events (`HttpsRequestSent`, `HttpsResponseReceived`, `HttpsRequestFailed`) | Yes |
| 5 exception classes rooted in `HttpsTransportException` | Yes |
| Integration tests against UA-.NETStandard | Yes — full round-trip (connect / read / disconnect) against `opcua-https-binary` |
| `JsonHttpsEncoding` (Part 6 §7.4.5) | Roadmap (community request, opt-in — no production server stack implements §7.4.5 today |
| `XmlSoapHttpsEncoding` (Part 6 §7.4.3) | Roadmap (community request, opt-in |
| `WsSoapTransport` — legacy `https://` SOAP/XML with WS-SecureConversation (Part 6 §7.3 + §6.6) | Roadmap (community request, opt-in (separate transport, not a strategy) |

## How it relates to `opcua-client`

`opcua-client` v4.4.0 added two small seams on `ClientTransportInterface`
to make non-TCP transports first-class:

- `createProbe(): self` — the discovery probe uses the same transport
  family as the main connection, not a hardcoded `TcpTransport`.
- `isSecureChannelExternal(): bool` — when `true`, the client skips the
  OPC UA `OpenSecureChannel` exchange. TLS already wraps the channel.

`HttpsTransport` returns `true` for the second and constructs a fresh
sibling for the first.

## Architecture

<!-- @code-block language="text" label="Architecture" -->
```
OpcUaClient → ClientTransportInterface
                    │
                    ▼
             HttpsTransport
                    │
        ┌───────────┼───────────┐
        ▼                       ▼
HttpsEncodingStrategy      HttpClientInterface
        │                       │
BinaryHttpsEncoding        CurlHttpClient (ext-curl)
    (§7.4.4)
        │
        │  encode: strip 24-byte UA-TCP prefix → bare service body
        │  decode: rewrap bare response in synthetic UA-TCP frame
        │  fakeAcknowledge: produce ACK locally (HEL/ACK is not on the wire)
```
<!-- @endcode-block -->
