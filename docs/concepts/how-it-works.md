---
eyebrow: 'Docs · Concepts'
lede:    'One UA service request = one HTTPS POST. TLS is the secure channel. The transport replaces UA-TCP framing with raw HTTP exchanges.'

see_also:
  - { href: '../api/transport.md',            meta: '4 min' }
  - { href: '../api/encoding-strategies.md',  meta: '3 min' }
  - { href: 'https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4', meta: 'external', label: 'OPC UA Part 6 §7.4' }

prev: { label: 'Quick start',  href: '../getting-started/quick-start.md' }
next: { label: 'Encodings', href: './encodings.md' }
---

# How HTTPS transport works

## The wire is HTTP

Every UA service call becomes a single HTTP POST. The body carries the
binary-encoded service request (NodeId TypeId + RequestHeader + body),
the `Content-Type` is the encoding's MIME, the response body carries
the matching service response. There is no persistent OPC UA channel
on the wire; TLS provides confidentiality and integrity.

| | UA-TCP (`opc.tcp://`) | HTTPS Binary (`opc.https://`) |
|---|---|---|
| HEL/ACK | on the wire | absent (transport fakes locally) |
| OpenSecureChannel | on the wire | absent (TLS is the channel) |
| MSGF framing | on the wire | absent (bare service body in HTTP) |
| SecureChannelId | meaningful | unused (synthetic in re-frames) |
| Content-Type | n/a | `application/octet-stream` for §7.4.4 binary |

## What the transport does

`HttpsTransport` implements `ClientTransportInterface` and intercepts the
two frames the core would otherwise send as UA-TCP:

<!-- @steps -->
- **HEL** — instead of writing it on the wire, the transport asks the
  encoding strategy for a synthetic ACK
  (`fakeAcknowledge(string $helFrame): string`) and primes it for the
  next `receive()`. No HTTP traffic is generated.

- **MSG / CLO** — the encoding strategy strips the 24-byte UA-TCP prefix
  (8-byte UA header + 4-byte SecureChannelId + 4-byte TokenId +
  8-byte SequenceHeader) and the resulting body is POSTed. The response
  body is re-wrapped in a synthetic UA-TCP frame so the core's decoder
  reads it transparently.
<!-- @endsteps -->

The OpenSecureChannel frame (`OPN`) is never even emitted by the core
when the transport reports `isSecureChannelExternal() === true`, because
`ManagesSecureChannelTrait::openSecureChannel()` short-circuits to
`openSecureChannelExternal()`, which only initialises a `SessionService`
with synthetic channel/token IDs.

## Lifecycle of a Client.connect()

<!-- @code-block language="text" label="connect() flow" -->
```
ClientBuilder::connect('opc.https://server.example:443/UA/')
    │
    ▼
performConnect()
    ├── discoverServerCertificate()
    │     ├── createProbe()              ← fresh HttpsTransport sibling
    │     ├── probe->connect()           ← no-op (HTTPS is stateless)
    │     ├── send(HEL)                  ← fake ACK
    │     ├── receive()                  ← returns the fake ACK
    │     ├── (skip OPN because isSecureChannelExternal())
    │     ├── send(GetEndpoints MSG)     ← real POST
    │     └── receive()                  ← re-framed response
    │
    ├── transport->connect()             ← no-op
    ├── doHandshake()                    ← HEL → fake ACK
    ├── openSecureChannelExternal()      ← no OPN, just synthetic SessionService
    └── createAndActivateSession()       ← POST(CreateSession), POST(ActivateSession)
```
<!-- @endcode-block -->

After this, every `read()` / `write()` / `browse()` / `call()` becomes one
POST against the configured `endpointUrl`.

## Security model

TLS handles confidentiality and integrity end-to-end:

- **Verify the server certificate** via `CurlHttpClient(verifyTls: true,
  caBundle: '...')`. Disable only inside controlled test environments.
- **Mutual TLS** is supplied via `clientCertPath` / `clientKeyPath` on
  `CurlHttpClient` — *not* via `ClientBuilder::setClientCertificate()`,
  which configures the OPC UA application certificate (a separate
  concept).
- The OPC UA secure channel **is not negotiated**; the
  `SecurityPolicy` / `SecurityMode` set on `ClientBuilder` should be
  `None` for HTTPS deployments unless the server explicitly supports a
  per-message UA secure channel on top of TLS (rare).

<!-- @callout variant="warning" title="Don't run verifyTls: false in production" -->
A network attacker can interpose silently. Always pin a CA bundle (or
rely on the system CA store) in real deployments.
<!-- @endcallout -->

## Failure modes

| Cause | Exception |
| --- | --- |
| Network / DNS / TLS handshake error | `HttpsRequestException` |
| Non-2xx HTTP response | `HttpsStatusException` (carries `statusCode` + `responseBody`) |
| Encoding strategy could not decode a body | `EncodingException` |
| Strategy explicitly does not support a frame type yet | `UnsupportedEncodingException` |
| Anything else from the transport orchestration | `HttpsTransportException` (base) |

Each failure dispatches a `HttpsRequestFailed` PSR-14 event when a
dispatcher is configured.
