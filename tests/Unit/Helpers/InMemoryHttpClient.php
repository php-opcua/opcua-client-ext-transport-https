<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps\Tests\Unit\Helpers;

use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsRequestException;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpClientInterface;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpRequest;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpResponse;

/**
 * Programmable HTTP client used by unit tests. Records every request and
 * replays a pre-loaded queue of responses (or exceptions) without touching
 * the network.
 */
final class InMemoryHttpClient implements HttpClientInterface
{
    /** @var list<HttpRequest> */
    public array $sent = [];

    /** @var list<HttpResponse|\Throwable> */
    private array $queue = [];

    public int $closeCalls = 0;

    public function enqueueResponse(HttpResponse $response): void
    {
        $this->queue[] = $response;
    }

    public function enqueueException(\Throwable $e): void
    {
        $this->queue[] = $e;
    }

    public function post(HttpRequest $request, float $timeoutSeconds): HttpResponse
    {
        $this->sent[] = $request;
        if ($this->queue === []) {
            throw new HttpsRequestException('InMemoryHttpClient queue is empty');
        }
        $item = array_shift($this->queue);
        if ($item instanceof \Throwable) {
            throw $item;
        }

        return $item;
    }

    public function close(): void
    {
        $this->closeCalls++;
    }
}
