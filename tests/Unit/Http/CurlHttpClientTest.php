<?php

declare(strict_types=1);

use PhpOpcua\Client\ExtTransportHttps\Exception\HttpsRequestException;
use PhpOpcua\Client\ExtTransportHttps\Http\CurlHttpClient;
use PhpOpcua\Client\ExtTransportHttps\Http\HttpRequest;

describe('CurlHttpClient', function () {

    it('instantiates with default options', function () {
        $client = new CurlHttpClient();
        expect($client)->toBeInstanceOf(CurlHttpClient::class);
        $client->close();
    });

    it('instantiates with TLS and mTLS options', function () {
        $client = new CurlHttpClient(
            verifyTls: true,
            caBundle: '/etc/ssl/certs/ca-bundle.crt',
            clientCertPath: '/path/cert.pem',
            clientKeyPath: '/path/key.pem',
            clientKeyPassword: 'secret',
            extraCurlOptions: [CURLOPT_USERAGENT => 'test'],
        );
        expect($client)->toBeInstanceOf(CurlHttpClient::class);
        $client->close();
    });

    it('translates a network failure into HttpsRequestException', function () {
        $client = new CurlHttpClient(verifyTls: false);
        $request = new HttpRequest(
            url: 'https://nonexistent-host-rc-test.invalid:65535/UA/',
            body: 'x',
            contentType: 'application/opcua+uabinary',
            acceptHeader: 'application/opcua+uabinary',
        );

        try {
            expect(fn () => $client->post($request, 1.0))
                ->toThrow(HttpsRequestException::class, 'cURL request to');
        } finally {
            $client->close();
        }
    });

    it('close() is idempotent', function () {
        $client = new CurlHttpClient();
        $client->close();
        $client->close();
        expect(true)->toBeTrue();
    });
});
