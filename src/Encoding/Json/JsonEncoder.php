<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding\Json;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PhpOpcua\Client\ExtTransportHttps\Exception\EncodingException;
use PhpOpcua\Client\Types\BuiltinType;
use PhpOpcua\Client\Types\DataValue;
use PhpOpcua\Client\Types\NodeId;
use PhpOpcua\Client\Types\Variant;

/**
 * OPC UA JSON encoder — reversible mode (Part 6 §5.4 / NuGet 1.5.378.134 output).
 *
 * Each public method returns either an associative array (a JSON object), a
 * scalar (string / int / float / bool), or `null`. A `null` return means the
 * value was a default and should be omitted from the parent JSON object — the
 * caller decides whether to keep or drop the field.
 *
 * Coverage in v4.4.0 is intentionally narrow (NodeId, Variant, DataValue,
 * StatusCode, DateTime). See `ROADMAP.md` for the remaining base types
 * (ExtensionObject, QualifiedName, LocalizedText, ByteString, GUID).
 */
final class JsonEncoder
{
    /** @return null|int|array<string,mixed> */
    public function encodeNodeId(?NodeId $value): null|int|array
    {
        if ($value === null) {
            return null;
        }

        $isNs0Numeric = $value->namespaceIndex === 0 && $value->isNumeric();
        $out = [];

        if (! $value->isNumeric()) {
            $out['IdType'] = match ($value->type) {
                NodeId::TYPE_STRING => 1,
                NodeId::TYPE_GUID => 2,
                NodeId::TYPE_OPAQUE => 3,
                default => throw new EncodingException("Unknown NodeId type: {$value->type}"),
            };
        }

        $out['Id'] = match ($value->type) {
            NodeId::TYPE_NUMERIC => (int) $value->identifier,
            NodeId::TYPE_STRING, NodeId::TYPE_GUID => (string) $value->identifier,
            NodeId::TYPE_OPAQUE => base64_encode((string) $value->identifier),
            default => throw new EncodingException("Unknown NodeId type: {$value->type}"),
        };

        if ($value->namespaceIndex !== 0) {
            $out['Namespace'] = $value->namespaceIndex;
        }

        if ($isNs0Numeric && count($out) === 1) {
            return $out;
        }

        return $out;
    }

    /** @return null|array<string,mixed> */
    public function encodeVariant(?Variant $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $body = $this->encodeVariantBody($value->type, $value->value);
        if ($body === null && $value->value === null) {
            return null;
        }

        $out = [
            'Type' => $value->type->value,
            'Body' => $body,
        ];

        if ($value->dimensions !== null && count($value->dimensions) > 1) {
            $out['Dimensions'] = $value->dimensions;
        }

        return $out;
    }

    /** @return null|array<string,mixed> */
    public function encodeDataValue(?DataValue $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $out = [];

        $variant = $value->getVariant();
        if ($variant !== null) {
            $encodedVariant = $this->encodeVariant($variant);
            if ($encodedVariant !== null) {
                $out['Value'] = $encodedVariant;
            }
        }

        $statusCode = $this->encodeStatusCode($value->statusCode);
        if ($statusCode !== null) {
            $out['StatusCode'] = $statusCode;
        }

        $sourceTs = $this->encodeDateTime($value->sourceTimestamp);
        if ($sourceTs !== null) {
            $out['SourceTimestamp'] = $sourceTs;
        }

        $serverTs = $this->encodeDateTime($value->serverTimestamp);
        if ($serverTs !== null) {
            $out['ServerTimestamp'] = $serverTs;
        }

        return $out;
    }

    public function encodeStatusCode(int $value): ?int
    {
        if ($value === 0) {
            return null;
        }

        return $value;
    }

    public function encodeDateTime(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $utc = new DateTimeImmutable('@' . $value->getTimestamp(), new DateTimeZone('UTC'));
        $micros = (int) $value->format('u');

        if ($micros === 0) {
            return $utc->format('Y-m-d\TH:i:s\Z');
        }

        $microsStr = str_pad((string) $micros, 6, '0', STR_PAD_LEFT);

        return $utc->format('Y-m-d\TH:i:s.') . $microsStr . 'Z';
    }

    /** @return mixed */
    private function encodeVariantBody(BuiltinType $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map(fn ($element) => $this->encodeVariantBody($type, $element), $value);
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
            BuiltinType::Guid => $value,
            BuiltinType::DateTime => $value instanceof DateTimeInterface
                ? $this->encodeDateTime($value)
                : $value,
            BuiltinType::ByteString => is_string($value) ? base64_encode($value) : $value,
            BuiltinType::NodeId => $value instanceof NodeId
                ? $this->encodeNodeId($value)
                : $value,
            BuiltinType::StatusCode => is_int($value) ? $value : $value,
            default => throw new EncodingException(
                "Variant body for BuiltinType::{$type->name} not supported in v4.4.0 JsonEncoder — see ROADMAP.md",
            ),
        };
    }
}
