---
eyebrow: 'Docs · Implementations'
lede:    'OPC UA HTTPS Binary (Part 6 §7.4.4). Production-ready transport for opc.https://; the body of each POST is the bare binary-encoded service request/response.'

see_also:
  - { href: '../api/encoding-strategies.md',                                              meta: '3 min' }
  - { href: '../concepts/how-it-works.md',                                                meta: '5 min' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.4',             meta: 'external', label: 'OPC UA Part 6 §7.4.4' }

prev: { label: 'Implementation status', href: './index.md' }
next: { label: 'HTTPS JSON',            href: './json.md' }
---

# HTTPS Binary (Part 6 §7.4.4)

<!-- @version-badge type="status" version="Shipped — v4.4.0" -->

The default and most widely-deployed HTTPS mapping. Every modern OPC UA stack that speaks `opc.https://` understands this — UA-.NETStandard's `HttpsTransportListener` accepts it natively, and it is what this package's integration test exercises end-to-end.

## What ships

| Surface | Status |
| --- | --- |
| `BinaryHttpsEncoding` strategy class | Yes |
| `Content-Type: application/octet-stream` | Yes — matches UA-.NETStandard's `HttpsTransportListener.kApplicationContentType` |
| 24-byte UA-TCP prefix strip on `encodeRequest()` | Yes — input must be `MSG` or `CLO` framed |
| Synthetic UA-TCP frame rebuild on `decodeResponse()` | Yes — channelId/tokenId/seq/req all fixed to `1` (read-and-discarded by core under external secure channel) |
| Local `HEL` → `ACK` fake exchange | Yes — no HTTP traffic; echoes client buffer sizes |
| Integration E2E against `uanetstandard-test-suite` v1.5.0+ `opcua-https-binary` | Yes — connect, read `i=2259`, disconnect |

## Wire shape

<!-- @code-block language="text" label="HTTP request envelope" -->
```
POST /UA/TestServer HTTP/1.1
Host: server.example:443
Content-Type: application/octet-stream
Accept: application/octet-stream
Content-Length: <N>

<NodeId TypeId><RequestHeader><service-request body>
```
<!-- @endcode-block -->

The body is the same bytes a UA-TCP peer would put on the wire **after** the 24-byte prefix (8-byte UA-TCP header + 4-byte SecureChannelId + 4-byte TokenId + 4-byte SequenceNumber + 4-byte RequestId). HTTPS replaces all that with TLS at the transport layer and a single POST round-trip.

## Authentication note

UA-.NETStandard's `HttpsServiceHost.CreateServiceHost(...)` filters the **Anonymous** user token policy out of the HTTPS endpoint description whenever `HttpsMutualTls = false` (no client cert at the TLS layer). The integration test in this package therefore connects with Username / Password (`admin` / `admin123` from the test-suite's seeded users); the channel itself stays `SecurityPolicy::None` because TLS provides confidentiality.

Anonymous over HTTPS works only when the server is configured with mTLS — supply a client certificate via `CurlHttpClient(clientCertPath: ..., clientKeyPath: ...)` in that case.

## When to use it

This is the default. Use it unless you have a concrete reason not to — every UA-.NETStandard, open62541-on-HTTPS, and most commercial server endpoints labelled "HTTPS" are HTTPS Binary under the hood. JSON and XML are very rarely advertised in real-world deployments.

## What's NOT in v4.4.0

- A streaming chunked-body mode. Each request is a single POST; the entire body is buffered in memory before send/receive. For multi-megabyte history reads, watch the configured `timeoutSeconds` budget and let cURL handle TCP/TLS keep-alive (it does so by default — see [Connection pooling](../recipes/connection-pooling.md)).

## See also

- [`BinaryHttpsEncoding` API reference](../api/encoding-strategies.md)
- [How HTTPS transport works](../concepts/how-it-works.md) — the orchestration around the encoding (HEL/ACK skip, OPN skip, lifecycle of `connect()`).
