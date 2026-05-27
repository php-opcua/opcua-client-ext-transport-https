<?php

declare(strict_types=1);

namespace PhpOpcua\Client\ExtTransportHttps;

use PhpOpcua\Client\Exception\ConnectionException;
use PhpOpcua\Client\Exception\ProtocolException;
use PhpOpcua\Client\ExtTransportHttps\Encoding\HttpsEncodingStrategy;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsRequestFailed;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsRequestSent;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsResponseReceived;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsRequestException;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsStatusException;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsTransportException;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpClientInterface;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpRequest;
use PhpOpcua\Client\Protocol\MessageHeader;
use PhpOpcua\Client\Transport\ClientTransportInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * `opc.https://` transport for OPC UA (Part 6 §7.4).
 *
 * Each UA message is exchanged as a single HTTPS POST: the request body is
 * produced by the configured {@see HttpsEncodingStrategy}, the response body
 * is decoded by the same strategy.
 *
 * The UA-TCP HEL/ACK handshake is not on the wire in §7.4. The transport
 * intercepts the HEL frame that `ManagesHandshakeTrait` emits as its first
 * call and delegates the ACK construction to the encoding strategy — so the
 * core `Client::connect()` pipeline (HEL → ACK → OPN → CreateSession → …)
 * runs unchanged.
 *
 * @see https://reference.opcfoundation.org/Core/Part6/v105/docs/7.4
 */
final class HttpsTransport implements ClientTransportInterface
{
    private readonly LoggerInterface $logger;

    private string $endpointUrl;

    private int $receiveBufferSize = 65535;

    /** Buffered ACK frame produced locally in response to the client's HEL. */
    private ?string $pendingAck = null;

    /** Buffered server response frame produced by the last POST. */
    private ?string $pendingResponse = null;

    private bool $closed = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly HttpsEncodingStrategy $encoding,
        string $endpointUrl,
        private readonly float $timeoutSeconds = 30.0,
        ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->endpointUrl = self::normaliseEndpointUrl($endpointUrl);
        $this->logger = $logger ?? new NullLogger();
    }

    public function connect(string $host, int $port, null|float $timeout = null): void
    {
        if ($this->closed) {
            throw new ConnectionException('HttpsTransport has been closed');
        }
    }

    public function send(string $data): void
    {
        if ($this->closed) {
            throw new ConnectionException('HttpsTransport has been closed');
        }

        if ($this->isHelloFrame($data)) {
            $this->pendingAck = $this->encoding->fakeAcknowledge($data);

            return;
        }

        $request = new HttpRequest(
            url: $this->endpointUrl,
            body: $this->encoding->encodeRequest($data),
            contentType: $this->encoding->contentType(),
            acceptHeader: $this->encoding->acceptHeader(),
        );

        $this->dispatch(new HttpsRequestSent(
            $this->endpointUrl,
            $request->contentType,
            strlen($request->body),
        ));

        try {
            $response = $this->httpClient->post($request, $this->timeoutSeconds);
        } catch (HttpsRequestException $e) {
            $this->dispatch(new HttpsRequestFailed($this->endpointUrl, 0, $e));
            $this->logger->error('HTTPS POST failed: {message}', ['message' => $e->getMessage()]);

            throw $e;
        }

        if (! $response->isSuccessful()) {
            $statusEx = new HttpsStatusException(
                sprintf('HTTPS POST returned status %d', $response->statusCode),
                $response->statusCode,
                $response->body,
            );
            $this->dispatch(new HttpsRequestFailed($this->endpointUrl, $response->statusCode, $statusEx));
            $this->logger->warning('HTTPS POST returned non-2xx: {status}', ['status' => $response->statusCode]);

            throw $statusEx;
        }

        $this->dispatch(new HttpsResponseReceived(
            $this->endpointUrl,
            $response->statusCode,
            strlen($response->body),
        ));

        $this->pendingResponse = $this->encoding->decodeResponse($response->body);
    }

    public function receive(): string
    {
        if ($this->closed) {
            throw new ConnectionException('HttpsTransport has been closed');
        }

        if ($this->pendingAck !== null) {
            $ack = $this->pendingAck;
            $this->pendingAck = null;

            return $ack;
        }

        if ($this->pendingResponse !== null) {
            $response = $this->pendingResponse;
            $this->pendingResponse = null;
            $this->assertMessageSizeFits($response);

            return $response;
        }

        throw new HttpsTransportException('receive() called without a pending response — out-of-order use of the transport');
    }

    public function setReceiveBufferSize(int $size): void
    {
        $this->receiveBufferSize = $size;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->pendingAck = null;
        $this->pendingResponse = null;
        $this->httpClient->close();
    }

    public function isConnected(): bool
    {
        return ! $this->closed;
    }

    public function createProbe(): self
    {
        return new self(
            httpClient: $this->httpClient,
            encoding: $this->encoding,
            endpointUrl: $this->endpointUrl,
            timeoutSeconds: $this->timeoutSeconds,
            logger: $this->logger,
            dispatcher: $this->dispatcher,
        );
    }

    /**
     * HTTPS supplies TLS as the secure channel — the core skips the OPC UA
     * OpenSecureChannel handshake when this returns `true`.
     */
    public function isSecureChannelExternal(): bool
    {
        return true;
    }

    private function isHelloFrame(string $frame): bool
    {
        return strlen($frame) >= MessageHeader::HEADER_SIZE
            && substr($frame, 0, 3) === 'HEL';
    }

    private function assertMessageSizeFits(string $frame): void
    {
        $length = strlen($frame);
        if ($length > $this->receiveBufferSize) {
            throw new ProtocolException(
                sprintf('Decoded UA frame size %d exceeds receive buffer %d', $length, $this->receiveBufferSize),
            );
        }
    }

    private function dispatch(object $event): void
    {
        try {
            $this->dispatcher?->dispatch($event);
        } catch (Throwable $e) {
            $this->logger->warning('Event dispatcher threw: {message}', ['message' => $e->getMessage()]);
        }
    }

    private static function normaliseEndpointUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            throw new HttpsTransportException("Invalid endpoint URL: {$url}");
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme === 'opc.https') {
            $url = 'https://' . substr($url, strlen('opc.https://'));
        } elseif ($scheme !== 'https') {
            throw new HttpsTransportException("Endpoint URL must use opc.https:// or https://, got: {$url}");
        }

        return $url;
    }
}
