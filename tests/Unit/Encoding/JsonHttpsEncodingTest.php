<?php

declare(strict_types=1);

use PhpOpcua\Client\Encoding\BinaryDecoder;
use PhpOpcua\Client\Encoding\BinaryEncoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonDecoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonEncoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\JsonHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Exception\UnsupportedEncodingException;
use PhpOpcua\Client\Protocol\AcknowledgeMessage;
use PhpOpcua\Client\Protocol\HelloMessage;
use PhpOpcua\Client\Protocol\MessageHeader;
use PhpOpcua\Client\Types\NodeId;

describe('JsonHttpsEncoding', function () {

    it('declares application/opcua+uajson for content and accept headers', function () {
        $encoding = new JsonHttpsEncoding();
        expect($encoding->contentType())->toBe('application/opcua+uajson');
        expect($encoding->acceptHeader())->toBe('application/opcua+uajson');
    });

    it('produces a valid ACK from a client HEL', function () {
        $hello = new HelloMessage(receiveBufferSize: 65535, sendBufferSize: 65535);
        $ack = (new JsonHttpsEncoding())->fakeAcknowledge($hello->encode());

        $decoder = new BinaryDecoder($ack);
        $header = MessageHeader::decode($decoder);
        expect($header->getMessageType())->toBe('ACK');

        $decoded = AcknowledgeMessage::decode($decoder);
        expect($decoded->getReceiveBufferSize())->toBe(65535);
    });

    it('rejects an unknown service TypeId with UnsupportedEncodingException', function () {
        $unknownBinaryTypeId = 9999;
        $bareBody = new BinaryEncoder();
        $bareBody->writeNodeId(NodeId::numeric(0, $unknownBinaryTypeId));
        $bareBodyBytes = $bareBody->getBuffer();

        $body = new BinaryEncoder();
        $body->writeUInt32(1);
        $body->writeUInt32(1);
        $body->writeUInt32(1);
        $body->writeUInt32(1);
        $body->writeRawBytes($bareBodyBytes);

        $totalSize = MessageHeader::HEADER_SIZE + strlen($body->getBuffer());
        $frame = new BinaryEncoder();
        (new MessageHeader('MSG', 'F', $totalSize))->encode($frame);
        $frame->writeRawBytes($body->getBuffer());

        (new JsonHttpsEncoding())->encodeRequest($frame->getBuffer());
    })->throws(UnsupportedEncodingException::class, 'no service codec registered for binary TypeId 9999');

    it('rejects a response with unknown JSON TypeId', function () {
        (new JsonHttpsEncoding())->decodeResponse('{"TypeId":{"Id":9999},"Body":{}}');
    })->throws(UnsupportedEncodingException::class, 'no service codec registered for JSON TypeId 9999');

    it('exposes the underlying JsonEncoder and JsonDecoder', function () {
        $encoding = new JsonHttpsEncoding();
        expect($encoding->getJsonEncoder())->toBeInstanceOf(JsonEncoder::class);
        expect($encoding->getJsonDecoder())->toBeInstanceOf(JsonDecoder::class);
    });

    it('accepts custom JsonEncoder / JsonDecoder via constructor', function () {
        $customEncoder = new JsonEncoder();
        $customDecoder = new JsonDecoder();
        $encoding = new JsonHttpsEncoding(encoder: $customEncoder, decoder: $customDecoder);
        expect($encoding->getJsonEncoder())->toBe($customEncoder);
        expect($encoding->getJsonDecoder())->toBe($customDecoder);
    });
});
