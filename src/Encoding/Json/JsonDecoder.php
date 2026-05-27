<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding\Json;

use DateTimeImmutable;
use DateTimeZone;
use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;
use PhpOpcua\Client\Types\BuiltinType;
use PhpOpcua\Client\Types\DataValue;
use PhpOpcua\Client\Types\NodeId;
use PhpOpcua\Client\Types\Variant;
use Throwable;

/**
 * OPC UA JSON decoder — reversible mode (Part 6 §5.4).
 *
 * Inverse of {@see JsonEncoder}. Accepts associative arrays decoded from JSON
 * and produces the corresponding `PhpOpcua\Client\Types\*` instance.
 *
 * Coverage in v4.4.0 mirrors `JsonEncoder` — see `ROADMAP.md` for the
 * remaining base types.
 */
final class JsonDecoder
{
    /** @param array<string,mixed> $data */
    public function decodeNodeId(array $data): NodeId
    {
        $idType = isset($data['IdType']) && is_int($data['IdType']) ? $data['IdType'] : 0;
        $namespace = isset($data['Namespace']) && is_int($data['Namespace']) ? $data['Namespace'] : 0;
        $id = $data['Id'] ?? null;

        return match ($idType) {
            0 => NodeId::numeric($namespace, is_int($id) ? $id : (int) $id),
            1 => NodeId::string($namespace, (string) $id),
            2 => NodeId::guid($namespace, (string) $id),
            3 => NodeId::opaque($namespace, is_string($id) ? base64_decode($id, true) ?: '' : ''),
            default => throw new EncodingException("Unknown NodeId IdType in JSON: {$idType}"),
        };
    }

    /** @param array<string,mixed> $data */
    public function decodeVariant(array $data): Variant
    {
        if (! isset($data['Type']) || ! is_int($data['Type'])) {
            throw new EncodingException('Variant JSON: missing or non-int "Type" field');
        }

        $type = BuiltinType::tryFrom($data['Type']);
        if ($type === null) {
            throw new EncodingException("Variant JSON: unknown BuiltinType {$data['Type']}");
        }

        $body = $data['Body'] ?? null;
        $value = $this->decodeVariantBody($type, $body);

        $dimensions = null;
        if (isset($data['Dimensions']) && is_array($data['Dimensions'])) {
            $dimensions = array_values(array_map('intval', $data['Dimensions']));
        }

        return new Variant($type, $value, $dimensions);
    }

    /** @param array<string,mixed> $data */
    public function decodeDataValue(array $data): DataValue
    {
        $variant = null;
        if (isset($data['Value']) && is_array($data['Value'])) {
            $variant = $this->decodeVariant($data['Value']);
        }

        $statusCode = isset($data['StatusCode']) && is_int($data['StatusCode'])
            ? $data['StatusCode']
            : 0;

        $sourceTs = isset($data['SourceTimestamp']) && is_string($data['SourceTimestamp'])
            ? $this->decodeDateTime($data['SourceTimestamp'])
            : null;

        $serverTs = isset($data['ServerTimestamp']) && is_string($data['ServerTimestamp'])
            ? $this->decodeDateTime($data['ServerTimestamp'])
            : null;

        return new DataValue($variant, $statusCode, $sourceTs, $serverTs);
    }

    public function decodeStatusCode(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_array($value) && isset($value['Code']) && is_int($value['Code'])) {
            return $value['Code'];
        }

        throw new EncodingException('StatusCode JSON must be an int or {"Code": int}');
    }

    public function decodeDateTime(string $iso): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
        } catch (Throwable $e) {
            throw new EncodingException("Invalid DateTime JSON value '{$iso}': " . $e->getMessage(), 0, $e);
        }
    }

    private function decodeVariantBody(BuiltinType $type, mixed $body): mixed
    {
        if ($body === null) {
            return null;
        }

        if (is_array($body) && array_is_list($body)) {
            return array_map(fn ($element) => $this->decodeVariantBody($type, $element), $body);
        }

        return match ($type) {
            BuiltinType::Boolean,
            BuiltinType::SByte,
            BuiltinType::Byte,
            BuiltinType::Int16,
            BuiltinType::UInt16,
            BuiltinType::Int32,
            BuiltinType::UInt32,
            BuiltinType::Int64,
            BuiltinType::UInt64,
            BuiltinType::Float,
            BuiltinType::Double,
            BuiltinType::String,
            BuiltinType::Guid => $body,
            BuiltinType::DateTime => is_string($body) ? $this->decodeDateTime($body) : $body,
            BuiltinType::ByteString => is_string($body) ? (base64_decode($body, true) ?: '') : $body,
            BuiltinType::NodeId => is_array($body) ? $this->decodeNodeId($body) : $body,
            BuiltinType::StatusCode => $this->decodeStatusCode($body),
            default => throw new EncodingException(
                "Variant body for BuiltinType::{$type->name} not supported in v4.4.0 JsonDecoder — see ROADMAP.md",
            ),
        };
    }
}
