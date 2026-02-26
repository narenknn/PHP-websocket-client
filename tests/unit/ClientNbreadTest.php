<?php

namespace Paragi\PhpWebsocket\tests\unit;

require_once __DIR__ . '/../mocks/TestableClient.php';
require_once __DIR__ . '/../mocks/ControlledStreamWrapper.php';
require_once __DIR__ . '/../mocks/StreamConfig.php';

use Paragi\PhpWebsocket\ConnectionException;
use Paragi\PhpWebsocket\tests\mocks\ControlledStreamWrapper;
use Paragi\PhpWebsocket\tests\mocks\StreamConfig;
use Paragi\PhpWebsocket\tests\mocks\TestableClient;
use PHPUnit\Framework\TestCase;

class ClientNbreadTest extends TestCase
{
    protected function setUp(): void
    {
        StreamConfig::reset();
        ControlledStreamWrapper::reset();
        ControlledStreamWrapper::register();
    }

    protected function tearDown(): void
    {
        ControlledStreamWrapper::unregister();
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

    private function createPingFrame(): string
    {
        return chr(0x89) . chr(0x00);
    }

    private function createCloseFrame(): string
    {
        return chr(0x88) . chr(0x00);
    }

    private function createContinuationFrame(string $payload, bool $final = false): string
    {
        $payloadLen = strlen($payload);
        $firstByte = $final ? 0x80 : 0x00;
        return chr($firstByte) . chr($payloadLen) . $payload;
    }

    private function maskData(string $data, string $mask): string
    {
        $masked = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $masked .= chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        }
        return $masked;
    }

    private function setupClient(string $data = '', int $chunkSize = -1, bool $noData = false): TestableClient
    {
        $key = base64_encode(random_bytes(16));
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: $accept\r\n" .
            "\r\n";
        
        ControlledStreamWrapper::setPendingData($handshakeResponse . $data);
        ControlledStreamWrapper::setPendingChunkSize($chunkSize);
        ControlledStreamWrapper::setPendingNoData($noData);
        
        $client = new TestableClient('127.0.0.1', 9999, '', $error, 10, false, false, '/', null, false);
        
        return $client;
    }

    public function testNbreadTextFrame(): void
    {
        $frame = $this->createTextFrame("Hello");
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals("Hello", $result);
    }

    public function testNbreadBinaryFrame(): void
    {
        $frame = $this->createBinaryFrame("\x00\x01\x02\x03");
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals("\x00\x01\x02\x03", $result);
    }

    public function testNbreadEmptyMessage(): void
    {
        $frame = chr(0x81) . chr(0x00);
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals("", $result);
    }

    public function testNbreadMaskedFrame(): void
    {
        $frame = $this->createMaskedTextFrame("Hello");
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals("Hello", $result);
    }

    public function testNbreadUnicodeMessage(): void
    {
        $message = "Hello 世界 🌍";
        $frame = $this->createTextFrame($message);
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals($message, $result);
    }

    public function testNbreadMediumPayload126(): void
    {
        $payload = str_repeat("a", 200);
        $frame = chr(0x82) . chr(126) . pack('n', 200) . $payload;
        
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals($payload, $result);
    }

    public function testNbreadLargePayload127(): void
    {
        $payload = str_repeat("a", 1000);
        $frame = chr(0x82) . chr(127) . pack('N', 0) . pack('N', 1000) . $payload;
        
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals($payload, $result);
    }

    public function testNbreadPartialHeaderFirstByte(): void
    {
        $frame = $this->createTextFrame("Hello");
        $client = $this->setupClient($frame, 1);

        $result = $client->nbread($error);

        $this->assertEquals("Hello", $result);
    }

    public function testNbreadPartialPayload(): void
    {
        $payload = "Hello";
        $frame = $this->createTextFrame($payload);
        $client = $this->setupClient($frame, 2);

        $result = $client->nbread($error);

        $this->assertEquals($payload, $result);
    }

    public function testNbreadMultipleCallsComplete(): void
    {
        $frame = $this->createTextFrame("Hello");
        $client = $this->setupClient($frame, 2);

        $result1 = $client->nbread($error);
        $this->assertEquals("He", $result1);

        $result2 = $client->nbread($error);
        $this->assertEquals("llo", $result2);
    }

    public function testNbreadStateResetAfterFrame(): void
    {
        $frame = $this->createTextFrame("Hello");
        $client = $this->setupClient($frame);

        $client->nbread($error);

        $this->assertEquals(0, $client->getNbState());
    }

    public function testNbreadPingFrame(): void
    {
        $pingFrame = $this->createPingFrame();
        $client = $this->setupClient($pingFrame);

        $result = $client->nbread($error);

        $this->assertEquals("", $result);
        $wrapper = TestableClient::getStreamWrapper();
        $written = $wrapper->getWrittenData();
        $this->assertNotEmpty($written);
        $this->assertEquals(0x8A, ord($written[0][0]));
    }

    public function testNbreadCloseFrame(): void
    {
        $closeFrame = $this->createCloseFrame();
        $client = $this->setupClient($closeFrame);

        $result = $client->nbread($error);

        $this->assertEquals("", $result);
        $this->assertFalse($client->isActive());
    }

    public function testNbreadContinuationFrame(): void
    {
        $chunk1 = $this->createContinuationFrame("Hello", false);
        $chunk2 = $this->createContinuationFrame("World", true);
        
        $client = $this->setupClient($chunk1 . $chunk2);

        $result = $client->nbread($error);

        $this->assertEquals("HelloWorld", $result);
    }

    public function testNbreadFragmentedMessage(): void
    {
        $frame1 = chr(0x01) . chr(5) . "Hello";
        $frame2 = chr(0x80) . chr(5) . "World";
        
        $client = $this->setupClient($frame1 . $frame2);

        $result = $client->nbread($error);

        $this->assertEquals("HelloWorld", $result);
    }

    public function testNbreadThrowsOnClosedStream(): void
    {
        $client = new TestableClient('127.0.0.1', 9999, '', $error, 10, false, false, '/', null, false);
        $client->setActive(false);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage("Attempt to read a closed stream");

        $client->nbread($error);
    }

    public function testNbreadInvalidOpcodeSkipped(): void
    {
        $invalidFrame = chr(0x0F) . chr(0x00);
        $validFrame = $this->createTextFrame("Hello");
        
        $client = $this->setupClient($invalidFrame . $validFrame);

        $result = $client->nbread($error);

        $this->assertEquals("Hello", $result);
    }

    public function testNbreadWithNonBlockingConstructor(): void
    {
        $frame = $this->createTextFrame("Hello");
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals("Hello", $result);
    }

    public function testNbreadNoDataReturnsEmpty(): void
    {
        $client = $this->setupClient("", -1, true);

        $result = $client->nbread($error);

        $this->assertEquals("", $result);
    }

    public function testNbreadMidStateResume(): void
    {
        $frame = $this->createTextFrame("Hello");
        $client = $this->setupClient($frame);

        $client->setNbState(1);
        $client->setNbPayloadLen(5);

        $result = $client->nbread($error);

        $this->assertEquals("Hello", $result);
    }

    public function testNbreadDataAccumulates(): void
    {
        $frame1 = $this->createTextFrame("First");
        $frame2 = $this->createTextFrame("Second");
        
        $client = $this->setupClient($frame1 . $frame2);

        $result1 = $client->nbread($error);
        $result2 = $client->nbread($error);

        $this->assertEquals("First", $result1);
        $this->assertEquals("Second", $result2);
    }

    public function testNbreadPartialMaskKey(): void
    {
        $mask = "\x01\x02\x03\x04";
        $maskedPayload = $this->maskData("Hi", $mask);
        $frame = chr(0x81) . chr(0x80 | 2) . $mask . $maskedPayload;
        
        $client = $this->setupClient($frame, 1);

        $result = $client->nbread($error);

        $this->assertEquals("Hi", $result);
    }

    public function testNbreadPartialPayloadLengthExt(): void
    {
        $payload = str_repeat("a", 200);
        $frame = chr(0x82) . chr(126) . pack('n', 200) . $payload;
        
        $client = $this->setupClient($frame, 1);

        $result = $client->nbread($error);

        $this->assertEquals($payload, $result);
    }

    public function testNreadBinaryDataWithNullBytes(): void
    {
        $payload = "\x00\x01\x00\x02\x00\xFF\x00\x00";
        $frame = $this->createBinaryFrame($payload);
        
        $client = $this->setupClient($frame);

        $result = $client->nbread($error);

        $this->assertEquals($payload, $result);
    }
}
