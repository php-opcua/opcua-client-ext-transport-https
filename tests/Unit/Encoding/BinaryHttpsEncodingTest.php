<?php

declare(strict_types=1);

use PhpOpcua\Client\Encoding\BinaryDecoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;
use PhpOpcua\Client\Protocol\AcknowledgeMessage;
use PhpOpcua\Client\Protocol\HelloMessage;
use PhpOpcua\Client\Protocol\MessageHeader;

function rcMakeMsgFrame(string $payload): string
{
    $body = "\x01\x00\x00\x00"
        . "\x01\x00\x00\x00"
        . "\x01\x00\x00\x00"
        . "\x01\x00\x00\x00"
        . $payload;
    $totalSize = MessageHeader::HEADER_SIZE + strlen($body);

    return 'MSG' . 'F' . pack('V', $totalSize) . $body;
}

describe('BinaryHttpsEncoding', function () {

    it('declares application/octet-stream for content and accept headers', function () {
        $encoding = new BinaryHttpsEncoding();
        expect($encoding->contentType())->toBe('application/octet-stream');
        expect($encoding->acceptHeader())->toBe('application/octet-stream');
    });

    it('encodeRequest strips the 24-byte UA-TCP prefix from MSG frames', function () {
        $encoding = new BinaryHttpsEncoding();
        $payload = 'SERVICE-REQUEST-BODY';
        $frame = rcMakeMsgFrame($payload);

        expect($encoding->encodeRequest($frame))->toBe($payload);
    });

    it('encodeRequest also accepts CLO frames', function () {
        $encoding = new BinaryHttpsEncoding();
        $payload = 'CLOSE-PAYLOAD';
        $body = str_repeat("\x00", 16) . $payload;
        $frame = 'CLO' . 'F' . pack('V', MessageHeader::HEADER_SIZE + strlen($body)) . $body;

        expect($encoding->encodeRequest($frame))->toBe($payload);
    });

    it('encodeRequest rejects a frame shorter than the 24-byte prefix', function () {
        $encoding = new BinaryHttpsEncoding();
        expect(fn () => $encoding->encodeRequest('MSGF' . pack('V', 8)))
            ->toThrow(EncodingException::class, 'shorter than the expected 24-byte prefix');
    });

    it('encodeRequest rejects a frame whose MessageType is not MSG or CLO', function () {
        $encoding = new BinaryHttpsEncoding();
        $body = str_repeat("\x00", 16);
        $bogus = 'XYZ' . 'F' . pack('V', MessageHeader::HEADER_SIZE + strlen($body)) . $body;

        expect(fn () => $encoding->encodeRequest($bogus))
            ->toThrow(EncodingException::class, 'expected MSG or CLO frame');
    });

    it('decodeResponse wraps the bare body in a synthetic UA-TCP frame', function () {
        $encoding = new BinaryHttpsEncoding();
        $payload = 'RESPONSE-PAYLOAD';

        $framed = $encoding->decodeResponse($payload);

        expect(substr($framed, 0, 3))->toBe('MSG');
        expect($framed[3])->toBe('F');
        expect(substr($framed, -strlen($payload)))->toBe($payload);
        $announcedSize = unpack('V', substr($framed, 4, 4))[1];
        expect($announcedSize)->toBe(strlen($framed));
    });

    it('decodeResponse rejects an empty body', function () {
        $encoding = new BinaryHttpsEncoding();
        expect(fn () => $encoding->decodeResponse(''))
            ->toThrow(EncodingException::class, 'empty');
    });

    it('fakeAcknowledge produces a valid ACK frame from a HEL frame', function () {
        $encoding = new BinaryHttpsEncoding(
            negotiatedMaxMessageSize: 1_048_576,
            negotiatedMaxChunkCount: 5,
        );
        $hel = (new HelloMessage(0, 65535, 65535, 0, 0, 'opc.https://srv:443'))->encode();

        $ack = $encoding->fakeAcknowledge($hel);

        expect(substr($ack, 0, 3))->toBe('ACK');
        expect($ack[3])->toBe('F');
        expect(strlen($ack))->toBe(MessageHeader::HEADER_SIZE + 5 * 4);

        $decoder = new BinaryDecoder($ack);
        $header = MessageHeader::decode($decoder);
        expect($header->getMessageType())->toBe('ACK');

        $msg = AcknowledgeMessage::decode($decoder);
        expect($msg->getReceiveBufferSize())->toBe(65535);
        expect($msg->getMaxMessageSize())->toBe(1_048_576);
        expect($msg->getMaxChunkCount())->toBe(5);
    });

    it('fakeAcknowledge rejects a frame whose MessageType is not HEL', function () {
        $encoding = new BinaryHttpsEncoding();
        expect(fn () => $encoding->fakeAcknowledge('MSG' . 'F' . pack('V', 8)))
            ->toThrow(EncodingException::class, 'Expected MessageType "HEL"');
    });

    it('fakeAcknowledge rejects an unparseable frame', function () {
        $encoding = new BinaryHttpsEncoding();
        expect(fn () => $encoding->fakeAcknowledge('XX'))
            ->toThrow(EncodingException::class);
    });

    it('encodeRequest + decodeResponse round-trip preserves the service payload', function () {
        $encoding = new BinaryHttpsEncoding();
        $serviceBody = 'service body bytes here';
        $request = rcMakeMsgFrame($serviceBody);

        $httpBody = $encoding->encodeRequest($request);
        expect($httpBody)->toBe($serviceBody);

        $reframed = $encoding->decodeResponse($httpBody);
        $secondPass = $encoding->encodeRequest($reframed);
        expect($secondPass)->toBe($serviceBody);
    });
});
