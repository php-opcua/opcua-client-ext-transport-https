## Summary

Brief description of the changes.

## Type of Change

- [ ] Bug fix
- [ ] New feature
- [ ] Enhancement / improvement
- [ ] Refactoring (no functional changes)
- [ ] Documentation
- [ ] Tests

## Changes

- Change 1
- Change 2

## Testing

- [ ] Unit tests added/updated
- [ ] Integration tests added/updated (run against `uanetstandard-test-suite` v1.5.0+ `opcua-https-binary` service on `https://localhost:4852/UA/TestServer`)
- [ ] JSON wire-format fixtures re-generated if the JSON codec or a service codec changed (`docker run --rm -v $(pwd):/work -w /work/tools/json-fixture-generator mcr.microsoft.com/dotnet/sdk:8.0 dotnet run -- /work/tests/Fixtures/UaNetStandard`)
- [ ] All existing tests pass (`./vendor/bin/pest`)

## Documentation

- [ ] `docs/` updated (if applicable)
- [ ] `docs/implementations/<binary|json|xml-soap|legacy-soap>.md` updated if the per-mapping coverage changed
- [ ] `README.md` updated (if API surface changed)
- [ ] `CHANGELOG.md` updated
- [ ] `ROADMAP.md` updated (if a roadmap item moved status)

## Checklist

- [ ] Code follows the existing style and conventions (`composer format:check`)
- [ ] No breaking changes to the public API (`HttpsTransport`, `HttpsEncodingStrategy`, `BinaryHttpsEncoding`, `JsonHttpsEncoding`, `ServiceCodecInterface`, `HttpClientInterface`, `CurlHttpClient`, events, exceptions)
- [ ] `opcua-client` (core) compatibility constraint in `composer.json` still satisfied
- [ ] Cross-platform considerations checked (no Unix-only APIs — the transport targets Linux/macOS/Windows)
