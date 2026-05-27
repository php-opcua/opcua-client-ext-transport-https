---
eyebrow: 'Docs · Recipes'
lede:    'Route HTTPS through a corporate proxy via cURL options. Honours HTTP_PROXY / NO_PROXY environment variables when nothing is supplied explicitly.'

see_also:
  - { href: '../api/http-client.md',         meta: '3 min' }
  - { href: './tls-and-cert-trust.md',       meta: '3 min' }

prev: { label: 'TLS and cert trust', href: './tls-and-cert-trust.md' }
next: { label: 'Connection pooling', href: './connection-pooling.md' }
---

# Corporate proxy

`CurlHttpClient` exposes the `extraCurlOptions` constructor argument
for anything cURL can do — including proxies.

## Use the environment variables

cURL honours `HTTPS_PROXY` (and `NO_PROXY`) by default. If your shell
exports them, no PHP-level change is needed:

<!-- @code-block language="bash" label="env" -->
```bash
export HTTPS_PROXY=http://proxy.corp.example:8080
export NO_PROXY=internal.corp.example
```
<!-- @endcode-block -->

## Set the proxy in code

<!-- @code-block language="php" label="explicit HTTP proxy" -->
```php
new CurlHttpClient(
    verifyTls: true,
    extraCurlOptions: [
        CURLOPT_PROXY => 'http://proxy.corp.example:8080',
        CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
    ],
);
```
<!-- @endcode-block -->

## Authenticated proxy

<!-- @code-block language="php" label="proxy + basic auth" -->
```php
new CurlHttpClient(
    verifyTls: true,
    extraCurlOptions: [
        CURLOPT_PROXY => 'http://proxy.corp.example:8080',
        CURLOPT_PROXYUSERPWD => 'username:password',
    ],
);
```
<!-- @endcode-block -->

For NTLM, add `CURLOPT_PROXYAUTH => CURLAUTH_NTLM` (the runtime needs
cURL with NTLM compiled in).

## SOCKS5

<!-- @code-block language="php" label="SOCKS5" -->
```php
new CurlHttpClient(
    extraCurlOptions: [
        CURLOPT_PROXY => 'socks5h://proxy.corp.example:1080',
    ],
);
```
<!-- @endcode-block -->

`socks5h://` resolves DNS through the proxy — usually what you want
behind restrictive firewalls.

## Bypass the proxy for specific hosts

<!-- @code-block language="php" label="bypass list" -->
```php
new CurlHttpClient(
    extraCurlOptions: [
        CURLOPT_PROXY => 'http://proxy.corp.example:8080',
        CURLOPT_NOPROXY => 'localhost,127.0.0.1,*.internal.corp.example',
    ],
);
```
<!-- @endcode-block -->
