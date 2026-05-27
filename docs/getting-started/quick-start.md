---
eyebrow: 'Docs · Getting started'
lede:    'Build an HttpsTransport, wire it into ClientBuilder, and connect to an opc.https:// endpoint. Three steps.'

see_also:
  - { href: '../api/transport.md',         meta: '4 min' }
  - { href: '../api/encoding-strategies.md', meta: '3 min' }
  - { href: '../api/http-client.md',       meta: '3 min' }

prev: { label: 'Installation',           href: './installation.md' }
next: { label: 'How HTTPS transport works', href: '../concepts/how-it-works.md' }
---

# Quick start

<!-- @steps -->
- **Build the transport**

  <!-- @code-block language="php" label="1 — build transport" -->
  ```php
  use PhpOpcua\Client\ExtTransportHttps\HttpsTransport;
  use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
  use PhpOpcua\Client\ExtTransportHttps\Http\CurlHttpClient;

  $transport = new HttpsTransport(
      httpClient: new CurlHttpClient(verifyTls: true, caBundle: '/etc/ssl/certs/ca-bundle.crt'),
      encoding: new BinaryHttpsEncoding(),
      endpointUrl: 'opc.https://server.example:443/UA/',
      timeoutSeconds: 30.0,
  );
  ```
  <!-- @endcode-block -->

  Both `opc.https://` and plain `https://` URLs are accepted as
  `endpointUrl`; the constructor normalises to `https://` internally.

- **Plug it into `ClientBuilder`**

  <!-- @code-block language="php" label="2 — wire builder" -->
  ```php
  use PhpOpcua\Client\ClientBuilder;
  use PhpOpcua\Client\Security\SecurityMode;
  use PhpOpcua\Client\Security\SecurityPolicy;

  $client = (new ClientBuilder())
      ->setSecurityPolicy(SecurityPolicy::None)
      ->setSecurityMode(SecurityMode::None)
      ->setTransport($transport)
      ->setUserCredentials('admin', 'admin123')
      ->connect('opc.https://server.example:443/UA/');
  ```
  <!-- @endcode-block -->

  The `Client::connect()` flow detects the external secure channel
  via `HttpsTransport::isSecureChannelExternal() === true` and skips
  the `OpenSecureChannel` handshake. TLS is the secure channel.
  Username/Password identity is used here because UA-.NETStandard
  filters Anonymous out of HTTPS endpoints when mTLS is off.

- **Use the client as usual**

  <!-- @code-block language="php" label="3 — operate" -->
  ```php
  $value = $client->read('i=2259');
  echo $value->getValue();   // 0 = Running

  $refs = $client->browse('i=85');
  foreach ($refs as $ref) {
      echo $ref->getBrowseName()->getName() . PHP_EOL;
  }

  $client->disconnect();
  ```
  <!-- @endcode-block -->

  Every OPC UA service call becomes one HTTPS POST under the hood.
<!-- @endsteps -->

## With mutual TLS

<!-- @code-block language="php" label="mTLS configuration" -->
```php
$transport = new HttpsTransport(
    httpClient: new CurlHttpClient(
        verifyTls: true,
        caBundle: '/etc/ssl/certs/ca-bundle.crt',
        clientCertPath: '/certs/client.pem',
        clientKeyPath: '/certs/client.key',
        clientKeyPassword: getenv('CLIENT_KEY_PASS') ?: null,
    ),
    encoding: new BinaryHttpsEncoding(),
    endpointUrl: 'opc.https://server.example:443/UA/',
);
```
<!-- @endcode-block -->

<!-- @callout variant="note" -->
`setClientCertificate()` on the builder is for OPC UA application-level
certificates; mTLS cert / key for the TLS layer go on `CurlHttpClient`.
<!-- @endcallout -->
