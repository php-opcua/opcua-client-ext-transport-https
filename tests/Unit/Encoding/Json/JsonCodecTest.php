<?php

declare(strict_types=1);

use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonDecoder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\Json\JsonEncoder;
use PhpOpcua\Client\Types\BuiltinType;
use PhpOpcua\Client\Types\DataValue;
use PhpOpcua\Client\Types\NodeId;
use PhpOpcua\Client\Types\Variant;

/**
 * Loads a fixture file produced by tools/json-fixture-generator (UA-.NETStandard
 * 1.5.378.134) and returns the inner "value" payload. The wrapper key keeps the
 * C# generator simple — every JsonEncoder.WriteX(fieldName, ...) call needs one.
 */
function jsonFixture(string $name): mixed
{
    $path = __DIR__ . "/../../../Fixtures/UaNetStandard/{$name}.json";
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException("Missing fixture: {$path}");
    }
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    return $decoded['value'] ?? null;
}

describe('JsonEncoder / JsonDecoder · NodeId', function () {

    it('encodes numeric ns=0 as bare {"Id"}', function () {
        $encoded = (new JsonEncoder())->encodeNodeId(NodeId::numeric(0, 2259));
        expect($encoded)->toBe(jsonFixture('nodeid_numeric_ns0'));
    });

    it('encodes numeric ns=2 with Namespace field', function () {
        $encoded = (new JsonEncoder())->encodeNodeId(NodeId::numeric(2, 42));
        expect($encoded)->toBe(jsonFixture('nodeid_numeric_ns2'));
    });

    it('encodes string ns=2 with IdType=1', function () {
        $encoded = (new JsonEncoder())->encodeNodeId(NodeId::string(2, 'MyNode'));
        expect($encoded)->toBe(jsonFixture('nodeid_string_ns2'));
    });

    it('encodes guid ns=2 with IdType=2', function () {
        $encoded = (new JsonEncoder())->encodeNodeId(
            NodeId::guid(2, '550e8400-e29b-41d4-a716-446655440000'),
        );
        $expected = jsonFixture('nodeid_guid_ns2');
        expect(strtolower((string) $encoded['Id']))->toBe(strtolower((string) $expected['Id']));
        expect($encoded['IdType'])->toBe($expected['IdType']);
        expect($encoded['Namespace'])->toBe($expected['Namespace']);
    });

    it('encodes opaque ns=2 with IdType=3 + base64 body', function () {
        $encoded = (new JsonEncoder())->encodeNodeId(
            NodeId::opaque(2, "\xDE\xAD\xBE\xEF"),
        );
        expect($encoded)->toBe(jsonFixture('nodeid_opaque_ns2'));
    });

    it('round-trips each id-type via decoder', function () {
        $encoder = new JsonEncoder();
        $decoder = new JsonDecoder();

        $cases = [
            NodeId::numeric(0, 2259),
            NodeId::numeric(2, 42),
            NodeId::string(2, 'MyNode'),
            NodeId::opaque(2, "\xDE\xAD\xBE\xEF"),
        ];
        foreach ($cases as $node) {
            /** @var array<string,mixed> $encoded */
            $encoded = $encoder->encodeNodeId($node);
            $back = $decoder->decodeNodeId($encoded);
            expect($back->namespaceIndex)->toBe($node->namespaceIndex);
            expect($back->type)->toBe($node->type);
            expect($back->identifier)->toBe($node->identifier);
        }
    });
});

describe('JsonEncoder / JsonDecoder · Variant', function () {

    it('encodes scalar Int32 as {"Type":6,"Body":42}', function () {
        $encoded = (new JsonEncoder())->encodeVariant(new Variant(BuiltinType::Int32, 42));
        expect($encoded)->toBe(jsonFixture('variant_scalar_int32'));
    });

    it('encodes scalar String', function () {
        $encoded = (new JsonEncoder())->encodeVariant(new Variant(BuiltinType::String, 'hello'));
        expect($encoded)->toBe(jsonFixture('variant_scalar_string'));
    });

    it('encodes scalar Boolean true', function () {
        $encoded = (new JsonEncoder())->encodeVariant(new Variant(BuiltinType::Boolean, true));
        expect($encoded)->toBe(jsonFixture('variant_scalar_bool_true'));
    });

    it('encodes array Int32', function () {
        $encoded = (new JsonEncoder())->encodeVariant(new Variant(BuiltinType::Int32, [1, 2, 3]));
        expect($encoded)->toBe(jsonFixture('variant_array_int32'));
    });

    it('round-trips a scalar variant', function () {
        $original = new Variant(BuiltinType::Int32, 42);
        $encoded = (new JsonEncoder())->encodeVariant($original);
        $back = (new JsonDecoder())->decodeVariant($encoded);
        expect($back->type)->toBe(BuiltinType::Int32);
        expect($back->value)->toBe(42);
    });
});

describe('JsonEncoder / JsonDecoder · DataValue', function () {

    it('encodes a value-only DataValue as just the Value field', function () {
        $dv = DataValue::ofInt32(123);
        $encoded = (new JsonEncoder())->encodeDataValue($dv);
        expect($encoded)->toBe(jsonFixture('datavalue_value_only'));
    });

    it('encodes a status-only DataValue with just StatusCode', function () {
        $dv = DataValue::bad(0x80020000);
        $encoded = (new JsonEncoder())->encodeDataValue($dv);
        expect($encoded)->toBe(jsonFixture('datavalue_status_only'));
    });

    it('round-trips a full DataValue', function () {
        $ts = new DateTimeImmutable('2026-05-27T10:30:45.123456Z');
        $original = new DataValue(
            new Variant(BuiltinType::Int32, 123),
            statusCode: 0x00A90000,
            sourceTimestamp: $ts,
            serverTimestamp: $ts,
        );
        $encoded = (new JsonEncoder())->encodeDataValue($original);
        $back = (new JsonDecoder())->decodeDataValue($encoded);
        expect($back->statusCode)->toBe(0x00A90000);
        expect($back->getValue())->toBe(123);
        expect($back->sourceTimestamp?->format('Y-m-d\TH:i:s.u\Z'))
            ->toBe('2026-05-27T10:30:45.123456Z');
    });
});

describe('JsonEncoder / JsonDecoder · StatusCode', function () {

    it('omits StatusCode when Good (0)', function () {
        expect((new JsonEncoder())->encodeStatusCode(0))->toBeNull();
        expect(jsonFixture('statuscode_good'))->toBeNull();
    });

    it('emits BadInvalidArgument as integer 0x80AB0000', function () {
        $encoded = (new JsonEncoder())->encodeStatusCode(0x80AB0000);
        expect($encoded)->toBe(jsonFixture('statuscode_bad_invalid_argument'));
    });

    it('decodes a bare integer to the same uint', function () {
        $back = (new JsonDecoder())->decodeStatusCode(0x80020000);
        expect($back)->toBe(0x80020000);
    });
});

describe('JsonEncoder / JsonDecoder · DateTime', function () {

    it('encodes a fixed timestamp with µs precision', function () {
        $ts = new DateTimeImmutable('2026-05-27T10:30:45.123456Z');
        $encoded = (new JsonEncoder())->encodeDateTime($ts);
        expect($encoded)->toBe(jsonFixture('datetime_fixed'));
    });

    it('encodes the epoch (1970-01-01) without fractional seconds', function () {
        $ts = new DateTimeImmutable('1970-01-01T00:00:00Z');
        $encoded = (new JsonEncoder())->encodeDateTime($ts);
        expect($encoded)->toBe(jsonFixture('datetime_epoch'));
    });

    it('round-trips ISO 8601 via decoder', function () {
        $iso = '2026-05-27T10:30:45.123456Z';
        $back = (new JsonDecoder())->decodeDateTime($iso);
        expect($back->format('Y-m-d\TH:i:s.u\Z'))->toBe($iso);
    });
});
