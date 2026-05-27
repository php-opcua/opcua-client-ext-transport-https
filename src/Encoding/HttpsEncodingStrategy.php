<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding;

use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;

/**
 * Per-encoding strategy plugged into {@see \PhpOpcua\Client\ExtTransportHttps\HttpsTransport}.
 *
 * The OPC UA HTTPS mappings (Part 6 §7.4) all share the same transport
 * mechanics — one POST per UA message — and differ only in how the body
 * is encoded. This contract abstracts that difference so the transport
 * can stay agnostic.
 *
 * The strategy also owns the synthetic HEL/ACK exchange: the OPC UA HTTPS
 * mappings do not carry the UA-TCP handshake on the wire, but the core
 * `ManagesHandshakeTrait` still emits a `HEL` frame as its first call.
 * The strategy intercepts that frame and produces a matching `ACK` locally
 * so the rest of the pipeline (OPN, CreateSession, …) runs unmodified.
 *
 * @see https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4
 */
interface HttpsEncodingStrategy
{
    /**
     * MIME type for the `Content-Type` request header.
     */
    public function contentType(): string;

    /**
     * MIME type expected back from the server, used as the `Accept` request
     * header. May equal {@see contentType()} for symmetric encodings.
     */
    public function acceptHeader(): string;

    /**
     * Encode a fully-framed UA-TCP message (header + body) into the byte
     * sequence that goes into the HTTPS POST body.
     *
     * For binary the operation is essentially a passthrough; for JSON and
     * XML-SOAP it is a full re-encoding into a structured payload.
     *
     * @throws EncodingException When the input frame cannot be encoded.
     */
    public function encodeRequest(string $uaTcpFrame): string;

    /**
     * Decode the HTTPS response body into a fully-framed UA-TCP message
     * the core pipeline can read.
     *
     * @throws EncodingException When the response cannot be parsed.
     */
    public function decodeResponse(string $httpBody): string;

    /**
     * Build an ACK frame that satisfies the buffer-negotiation contract
     * expected by `ManagesHandshakeTrait`, given the `HEL` frame the client
     * just emitted. No network I/O happens here — the ACK is produced
     * locally because Part 6 §7.4 mappings do not exchange HEL/ACK on the
     * wire.
     *
     * @throws EncodingException When the input is not a valid HEL frame.
     */
    public function fakeAcknowledge(string $helFrame): string;
}
