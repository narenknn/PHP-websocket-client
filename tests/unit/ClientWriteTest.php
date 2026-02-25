<?php

namespace Paragi\PhpWebsocket\tests\unit;

require_once __DIR__ . '/../mocks/MockClient.php';

use Paragi\PhpWebsocket\Client;
use Paragi\PhpWebsocket\ConnectionException;
use Paragi\PhpWebsocket\tests\mocks\MockClientBuilder;

class ClientWriteTest extends WebSocketTestCase
{
    public function testWriteBinaryFrameRoundTrip(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(true)
            ->build();

        $mockClient->write("Hello", true, true);
        $response = $mockClient->read();
        
        $this->assertEquals("Hello", $response);
    }

    public function testWriteTextFrameRoundTrip(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(true)
            ->build();

        $mockClient->write("Hello", true, false);
        $response = $mockClient->read();
        
        $this->assertEquals("Hello", $response);
    }

    public function testWriteNonFinalFrame(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(false)
            ->build();

        $mockClient->write("First chunk", false, true);
        
        $written = $mockClient->getWriteHistory();
        $frame = $written[0];
        
        $firstByte = ord($frame[0]);
        $this->assertEquals(0x00, $firstByte & 0x0F);
        $this->assertEquals(0x00, $firstByte & 0x80);
    }

    public function testWriteContinuationFrame(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(false)
            ->build();

        $mockClient->write("First", false, true);
        $mockClient->write("Second", true, true);
        
        $written = $mockClient->getWriteHistory();
        
        $this->assertCount(2, $written);
        $this->assertEquals(0x00, ord($written[0][0]) & 0x0F);
        $this->assertEquals(0x80, ord($written[1][0]) & 0x80);
    }

    public function testWriteSmallPayload(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $mockClient->write("Hi");
        
        $written = $mockClient->getWriteHistory();
        $frame = $written[0];
        
        $secondByte = ord($frame[1]);
        $payloadLen = $secondByte & 0x7F;
        
        $this->assertLessThan(126, $payloadLen);
    }

    public function testWriteMediumPayload126(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $payload = str_repeat("a", 200);
        $mockClient->write($payload);
        
        $written = $mockClient->getWriteHistory();
        $frame = $written[0];
        
        $secondByte = ord($frame[1]);
        $this->assertEquals(126, $secondByte & 0x7F);
        
        $extLen = unpack('n', substr($frame, 2, 2))[1];
        $this->assertEquals(200, $extLen);
    }

    public function testWriteMediumPayload65535(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $payload = str_repeat("a", 65535);
        $mockClient->write($payload);
        
        $written = $mockClient->getWriteHistory();
        $frame = $written[0];
        
        $secondByte = ord($frame[1]);
        $this->assertEquals(126, $secondByte & 0x7F);
        
        $extLen = unpack('n', substr($frame, 2, 2))[1];
        $this->assertEquals(65535, $extLen);
    }

    public function testWriteLargePayload127(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $payload = str_repeat("a", 65536);
        $mockClient->write($payload);
        
        $written = $mockClient->getWriteHistory();
        $frame = $written[0];
        
        $secondByte = ord($frame[1]);
        $this->assertEquals(127, $secondByte & 0x7F);
    }

    public function testWriteWithMasking(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $mockClient->write("Test");
        
        $written = $mockClient->getWriteHistory();
        $frame = $written[0];
        
        $secondByte = ord($frame[1]);
        $this->assertEquals(0x80, $secondByte & 0x80);
        
        $maskKey = substr($frame, 2, 4);
        $this->assertEquals(4, strlen($maskKey));
    }

    public function testWriteUnicodeMessageRoundTrip(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(true)
            ->build();

        $message = "Hello 世界 🌍";
        $mockClient->write($message, true, false);
        $response = $mockClient->read();
        
        $this->assertEquals($message, $response);
    }

    public function testWriteEmptyMessage(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $written = $mockClient->write("");
        
        $this->assertGreaterThan(0, $written);
        
        $history = $mockClient->getWriteHistory();
        $this->assertCount(1, $history);
    }

    public function testWriteBinaryDataRoundTrip(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(true)
            ->build();

        $binaryData = "\x00\x01\x02\x03\xFF\xFE\xFD";
        $mockClient->write($binaryData, true, true);
        $response = $mockClient->read();
        
        $this->assertEquals($binaryData, $response);
    }

    public function testMultipleWrites(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withEchoWrites(false)
            ->build();

        $mockClient->write("First");
        $mockClient->write("Second");
        $mockClient->write("Third");
        
        $written = $mockClient->getWriteHistory();
        $this->assertCount(3, $written);
    }

    public function testWriteReturnsBytesWritten(): void
    {
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->build();

        $message = "Test message";
        $result = $mockClient->write($message);
        
        $this->assertGreaterThan(0, $result);
    }
}
