<?php

namespace Paragi\PhpWebsocket\tests\unit;

require_once __DIR__ . '/../mocks/MockClient.php';

use Paragi\PhpWebsocket\Client;
use Paragi\PhpWebsocket\ConnectionException;
use Paragi\PhpWebsocket\tests\mocks\MockClientBuilder;

class ClientReadTest extends WebSocketTestCase
{
    public function testReadTextFrame(): void
    {
        $frame = $this->createTextFrame("Hello");
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("Hello", $result);
    }

    public function testReadBinaryFrame(): void
    {
        $frame = $this->createBinaryFrame("\x00\x01\x02\x03");
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("\x00\x01\x02\x03", $result);
    }

    public function testReadUnmaskedFrame(): void
    {
        $frame = chr(0x81) . chr(5) . "Hello";
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("Hello", $result);
    }

    public function testReadMaskedFrame(): void
    {
        $frame = $this->createMaskedTextFrame("Hello");
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("Hello", $result);
    }

    public function testReadFragmentedMessage(): void
    {
        $chunk1 = chr(0x01) . chr(5) . "Hello";
        $chunk2 = chr(0x80) . chr(5) . "World";
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($chunk1 . $chunk2)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("HelloWorld", $result);
    }

    public function testReadMediumPayload126(): void
    {
        $payload = str_repeat("a", 200);
        $frame = chr(0x82) . chr(126) . pack('n', 200) . $payload;
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals($payload, $result);
    }

    public function testReadLargePayload127(): void
    {
        $payload = str_repeat("a", 65536);
        $frame = chr(0x82) . chr(127) . pack('N', 0) . pack('N', 65536) . $payload;
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals($payload, $result);
    }

    public function testReadPingFrame(): void
    {
        $pingFrame = chr(0x89) . chr(0x00);
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($pingFrame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("", $result);
    }

    public function testReadCloseFrame(): void
    {
        $closeFrame = chr(0x88) . chr(0x00);
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($closeFrame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("", $result);
    }

    public function testReadUnicodeMessage(): void
    {
        $message = "Hello 世界 🌍";
        $frame = $this->createTextFrame($message);
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals($message, $result);
    }

    public function testReadEmptyMessage(): void
    {
        $frame = chr(0x81) . chr(0x00);
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("", $result);
    }

    public function testReadMultipleFrames(): void
    {
        $frame1 = $this->createTextFrame("First");
        $frame2 = $this->createTextFrame("Second");
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame1)
            ->build();

        $result1 = $mockClient->read();
        
        $mockClient2 = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame2)
            ->build();

        $result2 = $mockClient2->read();
        
        $this->assertEquals("First", $result1);
        $this->assertEquals("Second", $result2);
    }

    public function testReadWithControlFrames(): void
    {
        $ping = chr(0x89) . chr(0x00);
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($ping)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals("", $result);
    }

    private function createMaskedTextFrame(string $payload): string
    {
        $payloadLen = strlen($payload);
        $mask = "\x01\x02\x03\x04";
        $maskedPayload = $this->maskData($payload, $mask);
        
        if ($payloadLen < 126) {
            return chr(0x81) . chr(0x80 | $payloadLen) . $mask . $maskedPayload;
        } elseif ($payloadLen < 0xFFFF) {
            return chr(0x81) . chr(0x80 | 126) . pack('n', $payloadLen) . $mask . $maskedPayload;
        } else {
            return chr(0x81) . chr(0x80 | 127) . pack('N', 0) . pack('N', $payloadLen) . $mask . $maskedPayload;
        }
    }

    public function testReadBinaryDataWithNullBytes(): void
    {
        $payload = "\x00\x01\x00\x02\x00\xFF\x00\x00";
        $frame = $this->createBinaryFrame($payload);
        
        $mockClient = (new MockClientBuilder())
            ->withValidHandshake()
            ->withReadResponse($frame)
            ->build();

        $result = $mockClient->read();
        
        $this->assertEquals($payload, $result);
    }

    private function createTextFrame(string $payload): string
    {
        $payloadLen = strlen($payload);
        if ($payloadLen < 126) {
            return chr(0x81) . chr($payloadLen) . $payload;
        } elseif ($payloadLen < 0xFFFF) {
            return chr(0x81) . chr(126) . pack('n', $payloadLen) . $payload;
        } else {
            return chr(0x81) . chr(127) . pack('N', 0) . pack('N', $payloadLen) . $payload;
        }
    }

    private function createBinaryFrame(string $payload): string
    {
        $payloadLen = strlen($payload);
        if ($payloadLen < 126) {
            return chr(0x82) . chr($payloadLen) . $payload;
        } elseif ($payloadLen < 0xFFFF) {
            return chr(0x82) . chr(126) . pack('n', $payloadLen) . $payload;
        } else {
            return chr(0x82) . chr(127) . pack('N', 0) . pack('N', $payloadLen) . $payload;
        }
    }

    private function maskData(string $data, string $mask): string
    {
        $masked = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $masked .= chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        }
        return $masked;
    }
}
