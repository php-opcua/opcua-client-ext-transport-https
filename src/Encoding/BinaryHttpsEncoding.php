<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding;

use PhpOpcua\Client\Encoding\BinaryDecoder;
use PhpOpcua\Client\Encoding\BinaryEncoder;
use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;
use PhpOpcua\Client\Protocol\HelloMessage;
use PhpOpcua\Client\Protocol\MessageHeader;
use Throwable;

/**
 * OPC UA HTTPS Binary encoding strategy (Part 6 §7.4.4).
 *
 * The wire format of HTTPS binary is leaner than UA-TCP: each HTTP POST
 * body is a *bare* service request (NodeId TypeId + RequestHeader + body),
 * with no MSGF chunking, no SecureChannelId, no SecurityHeader, and no
 * SequenceHeader — TLS provides the secure channel below the OPC UA layer
 * (Part 6 §7.4).
 *
 * The strategy therefore strips the 24-byte UA-TCP prefix off frames the
 * core emits (MSGF + 4-byte ChannelId + 4-byte TokenId + 8-byte SequenceHeader)
 * and re-wraps each HTTP response body with a synthetic prefix that the core
 * decodes transparently. The OpenSecureChannel exchange is short-circuited
 * upstream — see `ClientTransportInterface::isSecureChannelExternal()`.
 *
 * @see https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.4
 */
final class BinaryHttpsEncoding implements HttpsEncodingStrategy
{
    public const CONTENT_TYPE = 'application/octet-stream';

    /** 8 UA-TCP header + 4 SecureChannelId + 4 TokenId + 4 SequenceNumber + 4 RequestId. */
    private const FRAME_PREFIX_SIZE = 24;

    /** Echoed back in synthetic responses; the core decodes-and-discards. */
    private const SYNTHETIC_CHANNEL_ID = 1;

    private const SYNTHETIC_TOKEN_ID = 1;

    public function __construct(
        private readonly int $negotiatedMaxMessageSize = 16 * 1024 * 1024,
        private readonly int $negotiatedMaxChunkCount = 0,
    ) {
    }

    public function contentType(): string
    {
        return self::CONTENT_TYPE;
    }

    public function acceptHeader(): string
    {
        return self::CONTENT_TYPE;
    }

    /**
     * Strip the UA-TCP framing so only the service request payload reaches
     * the HTTP body. Layout of the input frame:
     *
     *     [3 byte MessageType][1 byte ChunkType][4 byte MessageSize]
     *     [4 byte SecureChannelId]
     *     [4 byte TokenId]
     *     [4 byte SequenceNumber][4 byte RequestId]
     *     [NodeId TypeId + ServiceRequestHeader + body...]   ← what we want
     */
    public function encodeRequest(string $uaTcpFrame): string
    {
        if (strlen($uaTcpFrame) < self::FRAME_PREFIX_SIZE) {
            throw new EncodingException('UA-TCP frame is shorter than the expected 24-byte prefix');
        }
        $messageType = substr($uaTcpFrame, 0, 3);
        if ($messageType !== 'MSG' && $messageType !== 'CLO') {
            throw new EncodingException(
                sprintf('encodeRequest expected MSG or CLO frame, got "%s"', $messageType),
            );
        }

        return substr($uaTcpFrame, self::FRAME_PREFIX_SIZE);
    }

    /**
     * Wrap the bare response payload in the UA-TCP framing the core decoder
     * expects. The synthetic prefix uses fixed channel/token/sequence values
     * because the core reads-and-discards them when the secure channel is
     * supplied externally (TLS).
     */
    public function decodeResponse(string $httpBody): string
    {
        if ($httpBody === '') {
            throw new EncodingException('HTTPS response body is empty');
        }

        $body = new BinaryEncoder();
        $body->writeUInt32(self::SYNTHETIC_CHANNEL_ID);
        $body->writeUInt32(self::SYNTHETIC_TOKEN_ID);
        $body->writeUInt32(1);
        $body->writeUInt32(1);
        $body->writeRawBytes($httpBody);
        $bodyBytes = $body->getBuffer();

        $totalSize = MessageHeader::HEADER_SIZE + strlen($bodyBytes);
        $frame = new BinaryEncoder();
        (new MessageHeader('MSG', 'F', $totalSize))->encode($frame);
        $frame->writeRawBytes($bodyBytes);

        return $frame->getBuffer();
    }

    public function fakeAcknowledge(string $helFrame): string
    {
        try {
            $decoder = new BinaryDecoder($helFrame);
            $header = MessageHeader::decode($decoder);
        } catch (Throwable $e) {
            throw new EncodingException('Failed to read HEL frame header: ' . $e->getMessage(), 0, $e);
        }

        if ($header->getMessageType() !== 'HEL') {
            throw new EncodingException(
                sprintf('Expected MessageType "HEL", got "%s"', $header->getMessageType()),
            );
        }

        try {
            $hello = HelloMessage::decode($decoder);
        } catch (Throwable $e) {
            throw new EncodingException('Failed to decode HEL body: ' . $e->getMessage(), 0, $e);
        }

        $body = new BinaryEncoder();
        $body->writeUInt32(0);
        $body->writeUInt32($hello->getReceiveBufferSize());
        $body->writeUInt32($hello->getSendBufferSize());
        $body->writeUInt32($this->negotiatedMaxMessageSize);
        $body->writeUInt32($this->negotiatedMaxChunkCount);
        $bodyBytes = $body->getBuffer();

        $totalSize = MessageHeader::HEADER_SIZE + strlen($bodyBytes);
        $frame = new BinaryEncoder();
        (new MessageHeader('ACK', 'F', $totalSize))->encode($frame);
        $frame->writeRawBytes($bodyBytes);

        return $frame->getBuffer();
    }
}
