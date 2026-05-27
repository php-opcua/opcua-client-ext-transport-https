---
name: Bug Report
about: Report a bug or unexpected behavior in the HTTPS transport
title: "[BUG] "
labels: bug
assignees: ''
---

## Description

A clear and concise description of the bug.

## Steps to Reproduce

```php
use PhpOpcua\Client\ClientBuilder;
use PhpOpcua\Client\ExtTransportHttps\HttpsTransport;
use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Http\CurlHttpClient;

$transport = new HttpsTransport(
    httpClient: new CurlHttpClient(verifyTls: false),
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://server.example:443/UA/',
);
// Minimal code to reproduce the issue
```

## Expected Behavior

What you expected to happen.

## Actual Behavior

What actually happened. Include error messages or exceptions if applicable.

## Environment

- PHP version:
- `opcua-client-ext-transport-https` version:
- `opcua-client` (core) version:
- OPC UA server: (e.g., UA-.NETStandard, Eclipse Milo, Unified Automation, Prosys, Softing, Kepware, …)
- Server HTTPS endpoint configuration: (Content-Type accepted, mTLS on/off, user token policies advertised on the HTTPS endpoint)
- OS:

## Transport configuration

- Encoding strategy: (`BinaryHttpsEncoding`, `JsonHttpsEncoding`, custom)
- mTLS configured? (client cert + key on `CurlHttpClient`):
- TLS verification mode (`verifyTls`, custom `caBundle`):
- Behind a proxy? (`CURLOPT_PROXY`, `HTTPS_PROXY` env, NTLM, SOCKS5):
- OPC UA `SecurityPolicy` / `SecurityMode` set on `ClientBuilder`:

## Wire / log evidence

Paste the relevant log lines from the transport (PSR-3) and, if available, the HTTP request/response headers and body — for example via `CURLOPT_VERBOSE` in `extraCurlOptions`, or a packet capture trimmed to the failing exchange (`tcpdump`, Wireshark with TLS keys).

If the failure surfaces as an OPC UA `ServiceFault`, include the `StatusCode` value and any diagnostic info text.

## Additional Context

Any additional context, stack traces, or test reproductions. If the bug is in `JsonHttpsEncoding` / a service codec, mention which service TypeId was involved and which fixture(s) the failure was diffed against.
