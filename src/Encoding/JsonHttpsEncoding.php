<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding;

use PhpOpcua\Client\Encoding\BinaryDecoder;
use PhpOpcua\Client\Encoding\BinaryEncoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonDecoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonEncoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\Service\GetEndpointsCodec;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\Service\ServiceCodecInterface;
use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;
use PhpOpcua\Client\ExtTransportHttps\Exception\UnsupportedEncodingException;
use PhpOpcua\Client\Protocol\HelloMessage;
use PhpOpcua\Client\Protocol\MessageHeader;
use PhpOpcua\Client\Types\NodeId;
use Throwable;

/**
 * OPC UA HTTPS JSON encoding strategy (Part 6 §7.4.5).
 *
 * Wraps every service request/response in the JSON envelope
 * `{"TypeId": <NodeId>, "Body": <fields>}` matching `Opc.Ua.JsonEncoder`
 * 1.5.378.134 reversible-mode output, validated against
 * `tests/Fixtures/UaNetStandard/`.
 *
 * Service-message conversion (binary↔JSON for a specific OPC UA service) is
 * delegated to {@see ServiceCodecInterface} implementations registered on the
 * strategy. v4.4.0 ships {@see GetEndpointsCodec} as the reference codec;
 * additional services land by implementing the interface and calling
 * `register()`.
 *
 * @see https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4.5
 */
final class JsonHttpsEncoding implements HttpsEncodingStrategy
{
    public const CONTENT_TYPE = 'application/opcua+uajson';

    /** 8 UA-TCP header + 4 SecureChannelId + 4 TokenId + 4 SequenceNumber + 4 RequestId. */
    private const FRAME_PREFIX_SIZE = 24;

    private const SYNTHETIC_CHANNEL_ID = 1;

    private const SYNTHETIC_TOKEN_ID = 1;

    /** @var array<int,ServiceCodecInterface> Keyed by binaryRequestTypeId. */
    private array $codecsByRequestBinaryId = [];

    /** @var array<int,ServiceCodecInterface> Keyed by jsonResponseTypeId. */
    private array $codecsByResponseJsonId = [];

    public function __construct(
        private readonly int $negotiatedMaxMessageSize = 16 * 1024 * 1024,
        private readonly int $negotiatedMaxChunkCount = 0,
        private readonly JsonEncoder $encoder = new JsonEncoder(),
        private readonly JsonDecoder $decoder = new JsonDecoder(),
    ) {
        $this->register(new GetEndpointsCodec($this->encoder));
    }

    public function register(ServiceCodecInterface $codec): void
    {
        $this->codecsByRequestBinaryId[$codec->binaryRequestTypeId()] = $codec;
        $this->codecsByResponseJsonId[$codec->jsonResponseTypeId()] = $codec;
    }

    public function contentType(): string
    {
        return self::CONTENT_TYPE;
    }

    public function acceptHeader(): string
    {
        return self::CONTENT_TYPE;
    }

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

        $bareBody = substr($uaTcpFrame, self::FRAME_PREFIX_SIZE);
        $decoder = new BinaryDecoder($bareBody);
        $typeId = $decoder->readNodeId();

        if ($typeId->namespaceIndex !== 0 || ! $typeId->isNumeric()) {
            throw new UnsupportedEncodingException(
                "JsonHttpsEncoding: only ns=0 numeric TypeIds are supported (got {$typeId->toString()})",
            );
        }

        $binaryId = (int) $typeId->identifier;
        $codec = $this->codecsByRequestBinaryId[$binaryId] ?? null;
        if ($codec === null) {
            throw new UnsupportedEncodingException(sprintf(
                'JsonHttpsEncoding: no service codec registered for binary TypeId %d. '
                . 'See ROADMAP.md and JsonHttpsEncoding::register() to add one.',
                $binaryId,
            ));
        }

        $body = $codec->encodeRequestBody($decoder);

        $envelope = [
            'TypeId' => $this->encoder->encodeNodeId(NodeId::numeric(0, $codec->jsonRequestTypeId())),
            'Body' => $body,
        ];

        return (string) json_encode($envelope, JSON_UNESCAPED_SLASHES);
    }

    public function decodeResponse(string $httpBody): string
    {
        if ($httpBody === '') {
            throw new EncodingException('HTTPS response body is empty');
        }

        try {
            $envelope = json_decode($httpBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new EncodingException('HTTPS JSON response is not valid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (! is_array($envelope) || ! isset($envelope['TypeId'])) {
            throw new EncodingException('HTTPS JSON response missing top-level TypeId field');
        }

        $typeIdData = $envelope['TypeId'];
        $jsonId = is_array($typeIdData) && isset($typeIdData['Id']) && is_int($typeIdData['Id'])
            ? $typeIdData['Id']
            : null;
        if ($jsonId === null) {
            throw new EncodingException('HTTPS JSON response TypeId must be a NodeId object with an integer Id');
        }

        $codec = $this->codecsByResponseJsonId[$jsonId] ?? null;
        if ($codec === null) {
            throw new UnsupportedEncodingException(sprintf(
                'JsonHttpsEncoding: no service codec registered for JSON TypeId %d. See ROADMAP.md.',
                $jsonId,
            ));
        }

        $bodyJson = $envelope['Body'] ?? [];
        if (! is_array($bodyJson)) {
            throw new EncodingException('HTTPS JSON response Body must be an object');
        }

        $serviceBinary = $codec->decodeResponseBody($bodyJson);

        $typeIdBinary = new BinaryEncoder();
        $typeIdBinary->writeNodeId(NodeId::numeric(0, $codec->binaryResponseTypeId()));

        $synthetic = new BinaryEncoder();
        $synthetic->writeUInt32(self::SYNTHETIC_CHANNEL_ID);
        $synthetic->writeUInt32(self::SYNTHETIC_TOKEN_ID);
        $synthetic->writeUInt32(1);
        $synthetic->writeUInt32(1);
        $synthetic->writeRawBytes($typeIdBinary->getBuffer());
        $synthetic->writeRawBytes($serviceBinary);
        $bodyBytes = $synthetic->getBuffer();

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

    public function getJsonEncoder(): JsonEncoder
    {
        return $this->encoder;
    }

    public function getJsonDecoder(): JsonDecoder
    {
        return $this->decoder;
    }
}
