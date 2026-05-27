<?php

declare(strict_types=1);

use PhpOpcua\Client\Exception\ConnectionException;
use PhpOpcua\Client\Exception\ProtocolException;
use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsRequestFailed;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsRequestSent;
use PhpOpcua\Client\ExtTransportHttps\Event\HttpsResponseReceived;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsRequestException;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsStatusException;
use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsTransportException;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpResponse;
use PhpOpcua\Client\ExtTransportHttps\HttpsTransport;
use PhpOpcua\Client\ExtTransportHttps\Tests\Unit\Helpers\InMemoryHttpClient;
use PhpOpcua\Client\Protocol\HelloMessage;
use Psr\EventDispatcher\EventDispatcherInterface;

function htRecordingDispatcher(): EventDispatcherInterface
{
    return new class() implements EventDispatcherInterface {
        /** @var list<object> */
        public array $events = [];

        public function dispatch(object $event): object
        {
            $this->events[] = $event;

            return $event;
        }
    };
}

function htMakeTransport(InMemoryHttpClient $http, ?EventDispatcherInterface $dispatcher = null): HttpsTransport
{
    return new HttpsTransport(
        httpClient: $http,
        encoding: new BinaryHttpsEncoding(),
        endpointUrl: 'opc.https://localhost:443/UA/',
        timeoutSeconds: 5.0,
        dispatcher: $dispatcher,
    );
}

describe('HttpsTransport', function () {

    it('rejects an endpoint URL that is not opc.https:// or https://', function () {
        expect(fn () => new HttpsTransport(
            httpClient: new InMemoryHttpClient(),
            encoding: new BinaryHttpsEncoding(),
            endpointUrl: 'opc.tcp://server:4840',
        ))->toThrow(HttpsTransportException::class, 'must use opc.https:// or https://');
    });

    it('accepts both opc.https:// and plain https://', function () {
        $a = new HttpsTransport(new InMemoryHttpClient(), new BinaryHttpsEncoding(), 'opc.https://srv:443/UA/');
        $b = new HttpsTransport(new InMemoryHttpClient(), new BinaryHttpsEncoding(), 'https://srv:443/UA/');

        expect($a->isConnected())->toBeTrue();
        expect($b->isConnected())->toBeTrue();
    });

    it('connect() is a no-op when not closed', function () {
        $transport = htMakeTransport(new InMemoryHttpClient());
        $transport->connect('localhost', 443);
        expect($transport->isConnected())->toBeTrue();
    });

    it('connect() throws once closed', function () {
        $transport = htMakeTransport(new InMemoryHttpClient());
        $transport->close();

        expect(fn () => $transport->connect('localhost', 443))
            ->toThrow(ConnectionException::class, 'closed');
    });

    it('send(HEL) does not call the HTTP client and primes the ACK locally', function () {
        $http = new InMemoryHttpClient();
        $transport = htMakeTransport($http);

        $hel = (new HelloMessage(0, 65535, 65535, 0, 0, 'opc.https://x'))->encode();
        $transport->send($hel);

        expect($http->sent)->toHaveCount(0);
        $ack = $transport->receive();
        expect(substr($ack, 0, 3))->toBe('ACK');
    });

    it('send() posts the encoded request body and re-frames the response on next receive()', function () {
        $http = new InMemoryHttpClient();
        $http->enqueueResponse(new HttpResponse(200, 'BARE-RESPONSE-PAYLOAD'));

        $transport = htMakeTransport($http);
        $servicePayload = 'service-request-payload';
        $request = 'MSG' . 'F' . pack('V', 8 + 16 + strlen($servicePayload))
            . str_repeat("\x00", 16) . $servicePayload;
        $transport->send($request);

        expect($http->sent)->toHaveCount(1);
        expect($http->sent[0]->url)->toBe('https://localhost:443/UA/');
        expect($http->sent[0]->contentType)->toBe('application/octet-stream');
        expect($http->sent[0]->body)->toBe($servicePayload);

        $reframed = $transport->receive();
        expect(substr($reframed, 0, 3))->toBe('MSG');
        expect(substr($reframed, -21))->toBe('BARE-RESPONSE-PAYLOAD');
    });

    it('dispatches HttpsRequestSent + HttpsResponseReceived on a happy path', function () {
        $http = new InMemoryHttpClient();
        $http->enqueueResponse(new HttpResponse(200, 'MSGF' . pack('V', 8)));

        $dispatcher = htRecordingDispatcher();
        $transport = htMakeTransport($http, $dispatcher);
        $transport->send('MSG' . 'F' . pack('V', 32) . str_repeat("\x00", 24));

        $names = array_map(fn ($e) => (new ReflectionClass($e))->getShortName(), $dispatcher->events);
        expect($names)->toBe(['HttpsRequestSent', 'HttpsResponseReceived']);
        expect($dispatcher->events[0])->toBeInstanceOf(HttpsRequestSent::class);
        expect($dispatcher->events[1])->toBeInstanceOf(HttpsResponseReceived::class);
    });

    it('translates non-2xx responses into HttpsStatusException and dispatches HttpsRequestFailed', function () {
        $http = new InMemoryHttpClient();
        $http->enqueueResponse(new HttpResponse(503, 'service down'));

        $dispatcher = htRecordingDispatcher();
        $transport = htMakeTransport($http, $dispatcher);

        try {
            $transport->send('MSG' . 'F' . pack('V', 32) . str_repeat("\x00", 24));
            $this->fail('Expected HttpsStatusException');
        } catch (HttpsStatusException $e) {
            expect($e->statusCode)->toBe(503);
            expect($e->responseBody)->toBe('service down');
        }

        expect($dispatcher->events)->toHaveCount(2);
        expect($dispatcher->events[1])->toBeInstanceOf(HttpsRequestFailed::class);
        expect($dispatcher->events[1]->statusCode)->toBe(503);
    });

    it('propagates HttpsRequestException from the HTTP client', function () {
        $http = new InMemoryHttpClient();
        $http->enqueueException(new HttpsRequestException('connect refused'));

        $dispatcher = htRecordingDispatcher();
        $transport = htMakeTransport($http, $dispatcher);

        expect(fn () => $transport->send('MSG' . 'F' . pack('V', 32) . str_repeat("\x00", 24)))
            ->toThrow(HttpsRequestException::class, 'connect refused');

        $failures = array_filter($dispatcher->events, fn ($e) => $e instanceof HttpsRequestFailed);
        expect($failures)->toHaveCount(1);
        expect(array_values($failures)[0]->statusCode)->toBe(0);
    });

    it('receive() before send() raises HttpsTransportException', function () {
        $transport = htMakeTransport(new InMemoryHttpClient());

        expect(fn () => $transport->receive())
            ->toThrow(HttpsTransportException::class, 'without a pending response');
    });

    it('receive() enforces the configured receive buffer size on the reframed response', function () {
        $http = new InMemoryHttpClient();
        $http->enqueueResponse(new HttpResponse(200, str_repeat('A', 1024)));

        $transport = htMakeTransport($http);
        $transport->setReceiveBufferSize(64);
        $transport->send('MSG' . 'F' . pack('V', 32) . str_repeat("\x00", 24));

        expect(fn () => $transport->receive())
            ->toThrow(ProtocolException::class, 'exceeds receive buffer');
    });

    it('close() releases the http client and is idempotent', function () {
        $http = new InMemoryHttpClient();
        $transport = htMakeTransport($http);

        $transport->close();
        $transport->close();

        expect($http->closeCalls)->toBe(1);
        expect($transport->isConnected())->toBeFalse();
    });

    it('send() after close() raises ConnectionException', function () {
        $transport = htMakeTransport(new InMemoryHttpClient());
        $transport->close();

        expect(fn () => $transport->send('MSG' . 'F' . pack('V', 32) . str_repeat("\x00", 24)))
            ->toThrow(ConnectionException::class, 'closed');
    });

    it('receive() after close() raises ConnectionException', function () {
        $transport = htMakeTransport(new InMemoryHttpClient());
        $transport->close();

        expect(fn () => $transport->receive())
            ->toThrow(ConnectionException::class, 'closed');
    });
});
