<?php

declare(strict_types=1);

use PhpOpcua\Client\ClientBuilder;
use PhpOpcua\Client\ExtTransportHttps\Encoding\BinaryHttpsEncoding;
use PhpOpcua\Client\ExtTransportHttps\Http\CurlHttpClient;
use PhpOpcua\Client\ExtTransportHttps\HttpsTransport;
use PhpOpcua\Client\Security\SecurityMode;
use PhpOpcua\Client\Security\SecurityPolicy;

const HTTPS_ENDPOINT = 'opc.https://localhost:4852/UA/TestServer';

describe('HTTPS Binary E2E against UA-.NETStandard', function () {

    it('connects, reads i=2259, and disconnects', function () {
        $transport = new HttpsTransport(
            httpClient: new CurlHttpClient(verifyTls: false),
            encoding: new BinaryHttpsEncoding(),
            endpointUrl: HTTPS_ENDPOINT,
            timeoutSeconds: 10.0,
        );

        $client = (new ClientBuilder())
            ->setSecurityPolicy(SecurityPolicy::None)
            ->setSecurityMode(SecurityMode::None)
            ->setTransport($transport)
            ->setUserCredentials('admin', 'admin123')
            ->connect(HTTPS_ENDPOINT);

        try {
            expect($client->isConnected())->toBeTrue();
            $value = $client->read('i=2259');
            expect($value->getValue())->not->toBeNull();
            expect($value->getValue())->toBe(0);
        } finally {
            $client->disconnect();
        }
    })->group('integration');
});
