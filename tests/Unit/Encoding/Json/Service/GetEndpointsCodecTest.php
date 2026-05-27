<?php

declare(strict_types=1);

use PhpOpcua\Client\Encoding\BinaryEncoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\JsonHttpsEncoding;
use PhpOpcua\Client\Protocol\MessageHeader;

/**
 * Loads a paired binary+JSON fixture set produced by the C# generator.
 * Returns the raw service-message bytes (post-TypeId) wrapped in a synthetic
 * UA-TCP frame so the strategy's encodeRequest sees the same shape the core
 * would emit.
 */
function getEndpointsBinaryFrame(string $baseName): string
{
    $b64 = file_get_contents(__DIR__ . "/../../../../Fixtures/UaNetStandard/{$baseName}.bin.b64");
    if ($b64 === false) {
        throw new RuntimeException("Missing fixture: {$baseName}.bin.b64");
    }
    $serviceBytes = base64_decode(trim($b64), true);
    if ($serviceBytes === false) {
        throw new RuntimeException("Invalid base64 in {$baseName}.bin.b64");
    }

    $body = new BinaryEncoder();
    $body->writeUInt32(1);
    $body->writeUInt32(1);
    $body->writeUInt32(1);
    $body->writeUInt32(1);
    $body->writeRawBytes($serviceBytes);
    $bodyBytes = $body->getBuffer();

    $totalSize = MessageHeader::HEADER_SIZE + strlen($bodyBytes);
    $frame = new BinaryEncoder();
    (new MessageHeader('MSG', 'F', $totalSize))->encode($frame);
    $frame->writeRawBytes($bodyBytes);

    return $frame->getBuffer();
}

/** @return array<string,mixed> */
function getEndpointsJsonFixture(string $baseName): array
{
    $json = file_get_contents(__DIR__ . "/../../../../Fixtures/UaNetStandard/{$baseName}.json");
    if ($json === false) {
        throw new RuntimeException("Missing fixture: {$baseName}.json");
    }

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

describe('JsonHttpsEncoding · GetEndpoints round-trip', function () {

    it('encodes a binary GetEndpointsRequest into the JSON envelope from the C# fixture', function () {
        $frame = getEndpointsBinaryFrame('getendpoints_request_minimal');
        $jsonString = (new JsonHttpsEncoding())->encodeRequest($frame);
        $envelope = json_decode($jsonString, true, flags: JSON_THROW_ON_ERROR);

        $expected = getEndpointsJsonFixture('getendpoints_request_minimal');
        expect($envelope['TypeId'])->toBe($expected['TypeId']);
        expect($envelope['Body']['EndpointUrl'])->toBe($expected['Body']['EndpointUrl']);
        expect($envelope['Body']['RequestHeader']['RequestHandle'])->toBe($expected['Body']['RequestHeader']['RequestHandle']);
        expect($envelope['Body']['RequestHeader']['TimeoutHint'])->toBe($expected['Body']['RequestHeader']['TimeoutHint']);
        expect($envelope['Body']['RequestHeader']['Timestamp'])->toBe($expected['Body']['RequestHeader']['Timestamp']);
    });

    it('omits null/default fields (AuthenticationToken, AuditEntryId, LocaleIds) from the JSON', function () {
        $frame = getEndpointsBinaryFrame('getendpoints_request_minimal');
        $envelope = json_decode((new JsonHttpsEncoding())->encodeRequest($frame), true, flags: JSON_THROW_ON_ERROR);

        expect($envelope['Body']['RequestHeader'])->not->toHaveKey('AuthenticationToken');
        expect($envelope['Body']['RequestHeader'])->not->toHaveKey('AuditEntryId');
        expect($envelope['Body'])->not->toHaveKey('LocaleIds');
        expect($envelope['Body'])->not->toHaveKey('ProfileUris');
    });

    it('decodes a JSON GetEndpointsResponse into a synthetic UA-TCP frame the core can read', function () {
        $jsonFixture = file_get_contents(__DIR__ . '/../../../../Fixtures/UaNetStandard/getendpoints_response_empty.json');
        $frame = (new JsonHttpsEncoding())->decodeResponse((string) $jsonFixture);

        $header = substr($frame, 0, 8);
        expect(substr($header, 0, 3))->toBe('MSG');
        expect(strlen($frame))->toBeGreaterThan(24);

        $bodyAfterPrefix = substr($frame, 24);
        $firstFour = substr($bodyAfterPrefix, 0, 4);
        expect($firstFour)->toBe("\x01\x00\xAF\x01");
    });

    it('round-trips: encodes a binary request and the result is parseable JSON', function () {
        $frame = getEndpointsBinaryFrame('getendpoints_request_minimal');
        $jsonString = (new JsonHttpsEncoding())->encodeRequest($frame);
        $envelope = json_decode($jsonString, true, flags: JSON_THROW_ON_ERROR);

        expect($envelope)->toHaveKey('TypeId');
        expect($envelope)->toHaveKey('Body');
        expect($envelope['TypeId']['Id'])->toBe(426);
    });
});
