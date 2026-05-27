<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding\Json\Service;

use DateTimeImmutable;
use PhpOpcua\Client\Encoding\BinaryDecoder;
use PhpOpcua\Client\Encoding\BinaryEncoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonEncoder;
use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;
use PhpOpcua\Client\Types\NodeId;

/**
 * Binary↔JSON codec for the OPC UA `GetEndpoints` service (Part 4 §5.4.4).
 *
 * Encodes a `GetEndpointsRequest` binary body into the JSON shape emitted by
 * `Opc.Ua.JsonEncoder` 1.5.378.134 reversible mode (validated against
 * `tests/Fixtures/UaNetStandard/getendpoints_request_minimal.{bin.b64,json}`).
 *
 * Decodes a `GetEndpointsResponse` JSON body into the binary representation the
 * core `BinaryDecoder` accepts (validated against
 * `getendpoints_response_empty.{json,bin.b64}`).
 *
 * Fields omitted by the JSON when they hold default values (NULL NodeId,
 * empty arrays, null strings) are recreated as their binary default forms
 * during decode.
 */
final class GetEndpointsCodec implements ServiceCodecInterface
{
    private const REQUEST_BINARY_ID = 428;

    private const REQUEST_JSON_ID = 426;

    private const RESPONSE_BINARY_ID = 431;

    private const RESPONSE_JSON_ID = 429;

    public function __construct(
        private readonly JsonEncoder $jsonEncoder = new JsonEncoder(),
    ) {
    }

    public function binaryRequestTypeId(): int
    {
        return self::REQUEST_BINARY_ID;
    }

    public function jsonRequestTypeId(): int
    {
        return self::REQUEST_JSON_ID;
    }

    public function binaryResponseTypeId(): int
    {
        return self::RESPONSE_BINARY_ID;
    }

    public function jsonResponseTypeId(): int
    {
        return self::RESPONSE_JSON_ID;
    }

    /** @return array<string,mixed> */
    public function encodeRequestBody(BinaryDecoder $body): array
    {
        $authToken = $body->readNodeId();
        $timestamp = $body->readDateTime();
        $requestHandle = $body->readUInt32();
        $returnDiagnostics = $body->readUInt32();
        $auditEntryId = $body->readString();
        $timeoutHint = $body->readUInt32();

        $additionalHeaderTypeId = $body->readNodeId();
        $additionalHeaderMask = $body->readByte();
        if ($additionalHeaderMask !== 0) {
            throw new EncodingException(
                'GetEndpointsRequest: non-empty AdditionalHeader body not supported in v4.4.0 JsonHttpsEncoding',
            );
        }

        $endpointUrl = $body->readString();
        $localeIds = $this->readStringArray($body);
        $profileUris = $this->readStringArray($body);

        $header = [];
        if (! $this->isNullNodeId($authToken)) {
            $header['AuthenticationToken'] = $this->jsonEncoder->encodeNodeId($authToken);
        }
        if ($timestamp !== null) {
            $header['Timestamp'] = $this->jsonEncoder->encodeDateTime($timestamp);
        }
        $header['RequestHandle'] = $requestHandle;
        $header['ReturnDiagnostics'] = $returnDiagnostics;
        if ($auditEntryId !== null && $auditEntryId !== '') {
            $header['AuditEntryId'] = $auditEntryId;
        }
        $header['TimeoutHint'] = $timeoutHint;

        $out = ['RequestHeader' => $header];
        if ($endpointUrl !== null) {
            $out['EndpointUrl'] = $endpointUrl;
        }
        if ($localeIds !== []) {
            $out['LocaleIds'] = $localeIds;
        }
        if ($profileUris !== []) {
            $out['ProfileUris'] = $profileUris;
        }

        return $out;
    }

    /** @param array<string,mixed> $body */
    public function decodeResponseBody(array $body): string
    {
        $headerJson = $body['ResponseHeader'] ?? [];
        if (! is_array($headerJson)) {
            throw new EncodingException('GetEndpointsResponse: ResponseHeader must be an object');
        }

        $encoder = new BinaryEncoder();

        $timestampIso = is_string($headerJson['Timestamp'] ?? null) ? $headerJson['Timestamp'] : null;
        $timestamp = $timestampIso !== null
            ? new DateTimeImmutable($timestampIso)
            : new DateTimeImmutable('1601-01-01T00:00:00Z');
        $encoder->writeDateTime($timestamp);

        $encoder->writeUInt32($this->intOr($headerJson, 'RequestHandle', 0));
        $encoder->writeUInt32($this->intOr($headerJson, 'ServiceResult', 0));

        $encoder->writeByte(0);

        $stringTable = $headerJson['StringTable'] ?? null;
        $this->writeStringArray($encoder, is_array($stringTable) ? $stringTable : []);

        $encoder->writeNodeId(NodeId::numeric(0, 0));
        $encoder->writeByte(0);

        $endpoints = $body['Endpoints'] ?? null;
        if (! is_array($endpoints) || $endpoints === []) {
            $encoder->writeInt32(0);
        } else {
            throw new EncodingException(
                'GetEndpointsResponse: non-empty Endpoints array not supported in v4.4.0 JsonHttpsEncoding — see ROADMAP.md',
            );
        }

        return $encoder->getBuffer();
    }

    /** @return string[] */
    private function readStringArray(BinaryDecoder $body): array
    {
        $count = $body->readInt32();
        if ($count <= 0) {
            return [];
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = (string) $body->readString();
        }

        return $out;
    }

    /** @param string[] $values */
    private function writeStringArray(BinaryEncoder $encoder, array $values): void
    {
        $encoder->writeInt32(count($values));
        foreach ($values as $value) {
            $encoder->writeString($value);
        }
    }

    private function isNullNodeId(NodeId $nodeId): bool
    {
        return $nodeId->namespaceIndex === 0
            && $nodeId->isNumeric()
            && (int) $nodeId->identifier === 0;
    }

    /** @param array<string,mixed> $haystack */
    private function intOr(array $haystack, string $key, int $default): int
    {
        $value = $haystack[$key] ?? null;

        return is_int($value) ? $value : $default;
    }
}
