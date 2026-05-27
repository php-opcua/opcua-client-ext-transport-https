---
name: Feature Request
about: Suggest a new feature or improvement for the HTTPS transport
title: "[FEATURE] "
labels: enhancement
assignees: ''
---

## Description

A clear and concise description of the feature you'd like.

## Use Case

Describe the problem this feature would solve or the scenario where it would be useful — for example, a server that requires a specific encoding (JSON service codec, XML SOAP, legacy SOAP/HTTP + WS-SecureConversation), a TLS / mTLS configuration the current client cannot reach, an HTTP backend you need plugged in, an event you would like dispatched.

## Proposed Solution

How you think this could be implemented (optional).

## Alternatives Considered

Any alternative solutions or workarounds you've considered — including whether the feature might belong in `php-opcua/opcua-client` (the core) instead of this extension.

## For non-Binary encodings (JSON, XML SOAP, legacy SOAP/HTTP)

The non-Binary encodings are tracked as community-driven in [`ROADMAP.md`](../../ROADMAP.md) because no production server stack implements them end-to-end. If you're asking for one of them, please include:

- The vendor + version of the OPC UA server you target.
- Whether you can capture a request/response sample on the wire.
- Whether you can publicly share that sample as a test fixture.

That information moves the encoding from "roadmap" to a concrete release plan.

## Additional Context

Any additional context, references to OPC UA Part 6 §7.4 / §7.3 / §5.4 (or other spec sections), and examples from other OPC UA stacks (UA-.NETStandard, Eclipse Milo, open62541, node-opcua, asyncua, commercial vendors) that already implement the feature you have in mind.
