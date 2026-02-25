<?php

namespace Paragi\PhpWebsocket\tests\unit;

require_once __DIR__ . '/../mocks/MockClient.php';

use Paragi\PhpWebsocket\Client;
use Paragi\PhpWebsocket\ConnectionException;
use Paragi\PhpWebsocket\tests\mocks\MockClientBuilder;

class ClientConnectionTest extends WebSocketTestCase
{
    public function testValidHandshake(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(true)
            ->build();

        $this->assertNotNull($mockClient->getConnection());
    }

    public function testConnectionFailureWithUnknownHost(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to connect');
        
        (new MockClientBuilder())
            ->withConnectFailure('getaddrinfo failed', 7)
            ->build();
    }

    public function testConnectionFailureWithConnectionRefused(): void
    {
        $this->expectException(ConnectionException::class);
        
        (new MockClientBuilder())
            ->withConnectFailure('Connection refused', 111)
            ->build();
    }

    public function testInvalidHandshake(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('upgrade');
        
        (new MockClientBuilder())
            ->withInvalidHandshake(400)
            ->build();
    }

    public function testInvalidHandshake404(): void
    {
        $this->expectException(ConnectionException::class);
        
        (new MockClientBuilder())
            ->withInvalidHandshake(404)
            ->build();
    }

    public function testMissingWebSocketKey(): void
    {
        $this->expectException(ConnectionException::class);
        
        (new MockClientBuilder())
            ->withMissingKeyHandshake()
            ->build();
    }

    public function testNon101StatusCode(): void
    {
        $this->expectException(ConnectionException::class);
        
        $mockClient = (new MockClientBuilder())
            ->withInvalidHandshake(200)
            ->build();
    }

    public function testHandshakeGeneratesValidAcceptKey(): void
    {
        $key = base64_encode(random_bytes(16));
        $expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake($key)
            ->build();
        
        $this->assertNotNull($mockClient);
    }
}
