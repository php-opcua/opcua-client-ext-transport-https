<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Encoding\Json\Service;

use PhpOpcua\Client\Encoding\BinaryDecoder;

/**
 * Per-service binary↔JSON codec plugged into {@see \PhpOpcua\Client\ExtTransportHttps\Encoding\JsonHttpsEncoding}.
 *
 * The HTTPS JSON mapping (Part 6 §7.4.5) wraps every service request/response
 * as `{"TypeId": <NodeId>, "Body": <fields>}`. The TypeId in JSON is the
 * abstract DataType numeric id (e.g. 426 for GetEndpointsRequest), distinct
 * from the DefaultBinary encoding id used by the binary wire (e.g. 428).
 *
 * Each implementation knows the four IDs for its service and how to translate
 * between the binary request body the core PHP `BinaryEncoder` produces and
 * the JSON shape a UA-.NETStandard `Opc.Ua.JsonEncoder` peer would emit.
 *
 * Adding a new service is mechanical: implement this interface and register
 * the codec via `JsonHttpsEncoding::register()`.
 */
interface ServiceCodecInterface
{
    /** DefaultBinary encoding numeric id of the request (e.g. 428 for GetEndpoints). */
    public function binaryRequestTypeId(): int;

    /** Abstract DataType numeric id of the request (e.g. 426 for GetEndpoints). */
    public function jsonRequestTypeId(): int;

    /** DefaultBinary encoding numeric id of the response (e.g. 431 for GetEndpoints). */
    public function binaryResponseTypeId(): int;

    /** Abstract DataType numeric id of the response (e.g. 429 for GetEndpoints). */
    public function jsonResponseTypeId(): int;

    /**
     * Decode the request body from the supplied binary cursor (positioned just
     * after the TypeId NodeId) and return a PHP array matching the `Body` field
     * of the JSON envelope this codec produces.
     *
     * @return array<string,mixed>
     */
    public function encodeRequestBody(BinaryDecoder $body): array;

    /**
     * Encode the response body from the supplied JSON array (the `Body` field
     * of the JSON envelope) and return the binary representation the core
     * `BinaryDecoder` can read.
     *
     * @param array<string,mixed> $body
     */
    public function decodeResponseBody(array $body): string;
}
