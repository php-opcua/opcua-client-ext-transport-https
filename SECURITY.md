# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 4.x     | Yes       |

## Reporting a Vulnerability

If you discover a security vulnerability in this library, please report
it responsibly.

**Do not open a public issue.** Send an email to
[gianfri@php-opcua.com](mailto:gianfri@php-opcua.com) with:

- A description of the vulnerability
- Steps to reproduce
- The affected version(s)
- Any potential impact assessment

You should receive an acknowledgment within 48 hours.

## Scope

This policy covers `php-opcua/opcua-client-ext-transport-https`. For
vulnerabilities in the core or related packages, report them to the
respective maintainers:

- [opcua-client](https://github.com/php-opcua/opcua-client) — the core
  client (binary protocol, secure channel, session, services)
- [opcua-client-ext-reverse-connect](https://github.com/php-opcua/opcua-client-ext-reverse-connect)
- [opcua-client-ext-pubsub](https://github.com/php-opcua/opcua-client-ext-pubsub)

## Security Considerations

HTTPS shifts the secure channel from the OPC UA layer (UA-TCP `OPN`) to
the TLS layer. That changes both what protects the wire and what the
application must configure.

### TLS is the secure channel

`HttpsTransport::isSecureChannelExternal() === true`. The OPC UA
`OpenSecureChannel` exchange is short-circuited; confidentiality and
integrity rely entirely on TLS. When deploying in production:

- **Verify the server certificate.** Use
  `CurlHttpClient(verifyTls: true, caBundle: '...')` against a trusted
  CA store or an explicit bundle. Never deploy with
  `verifyTls: false` outside controlled test environments.
- **Pin a CA bundle when the server uses an internal CA** — relying on
  the system CA store gives every public CA implicit trust.
- **Consider mutual TLS.** Provide `clientCertPath` / `clientKeyPath` on
  the `CurlHttpClient`. UA-.NETStandard's HTTPS listener can require
  mTLS via `<HttpsMutualTls>true</HttpsMutualTls>` and will reject
  connections without a matching client cert.
- **TLS 1.2 minimum.** cURL negotiates 1.2+ by default; pin TLS 1.3 with
  `CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3` if your server
  supports it.

### `SecurityPolicy` / `SecurityMode` should be `None`

Inside the OPC UA layer the secure channel is **not** negotiated — TLS
already wraps the channel. Configure `SecurityPolicy::None` +
`SecurityMode::None` on the builder when using HTTPS, unless the server
explicitly supports a per-message UA secure channel on top of TLS
(rare and non-standard).

### Authentication

The OPC UA user token (Anonymous / Username-Password / X.509) is
unaffected by the transport choice — `ClientBuilder::setUserCredentials`
and `setUserCertificate()` apply as on `opc.tcp://`. mTLS at the TLS
layer authenticates the TLS connection, **not** the OPC UA session.

### Proxies and middleboxes

`CurlHttpClient` honours `HTTPS_PROXY` / `NO_PROXY` and exposes
`CURLOPT_PROXY*` via `extraCurlOptions`. Proxies that intercept TLS
(MitM / corporate inspection appliances) defeat the security model
unless their CA is in the trust store; verify the chain you actually
end up with via `verifyTls: true` against a deliberate bundle.

### Logging and event handlers

`HttpsRequestSent` / `HttpsResponseReceived` carry the URL, content
type, status code, and body length — not the body itself. Custom
`extraHeaders` may carry credentials; **do not** log
`HttpRequest::$extraHeaders` verbatim.

## Sharing Debug Logs and Reproducers

In public channels (issues, discussions, gists), redact or omit:

| Information | Why |
|---|---|
| Endpoint URLs and the host that runs the listener | Reveals which servers are reachable from where |
| Trust-bundle paths, mTLS cert thumbprints | Identifies internal CAs and key material |
| `Authorization` / proxy credentials passed via `extraHeaders` | Plain credentials |
| OPC UA session IDs, authentication tokens | Short-lived but exploitable while valid |

For unmasked logs send them privately to the maintainer email above,
referencing the issue / discussion number in the subject.
