<?php

namespace Paragi\PhpWebsocket\tests\integration;

use Paragi\PhpWebsocket\Client;
use Paragi\PhpWebsocket\ConnectionException;
use Paragi\PhpWebsocket\tests\traits\UseLocalhostOption;
use PHPUnit\Framework\TestCase;

class ClientReadIntegrationTest extends TestCase
{
    use UseLocalhostOption;

    private const ECHO_SERVER = 'echo.websocket.org';
    private const ECHO_PORT = 443;
    private const ECHO_PATH = '/';
    private const TIMEOUT = 30;

    private const LOCAL_SERVER = 'localhost';
    private const LOCAL_PORT = 9999;

    private function getServer(): string
    {
        return self::useLocalhost() ? self::LOCAL_SERVER : self::ECHO_SERVER;
    }

    private function getPort(): int
    {
        return self::useLocalhost() ? self::LOCAL_PORT : self::ECHO_PORT;
    }

    private function createClient(bool $blocking = true): Client
    {
        try {
            return new Client(
                $this->getServer(),
                $this->getPort(),
                [],
                $error,
                self::TIMEOUT,
                true,
                false,
                self::ECHO_PATH,
                null,
                $blocking
            );
        } catch (ConnectionException $e) {
            if (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), 'Rate limit') !== false) {
                $this->markTestSkipped('Echo server rate limited - try again later');
            }
            throw $e;
        }
    }

    public function testReadReceivesData(): void
    {
        $client = $this->createClient(true);
        $message = "test message";
        $client->write($message);
        $response = $client->read($error);
        $this->assertNotEmpty($response);
        $this->assertIsString($response);
    }

    public function testReadUnicodeMessage(): void
    {
        $client = $this->createClient(true);
        $message = "Hello 世界 🌍";
        $client->write($message);
        $response = $client->read($error);
        $this->assertNotEmpty($response);
    }

    public function testReadBinaryData(): void
    {
        $client = $this->createClient(true);
        $message = "\x00\x01\x02\x03\xFF\xFE\xFD";
        $client->write($message, true, true);
        $response = $client->read($error);
        $this->assertNotEmpty($response);
    }

    public function testReadLargeMessage(): void
    {
        $client = $this->createClient(true);
        $message = str_repeat("a", 1000);
        $client->write($message);
        $response = $client->read($error);
        $this->assertNotEmpty($response);
    }

    public function testNbreadReceivesData(): void
    {
        $client = $this->createClient(false);
        $message = "test message";
        $client->write($message);
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $response = $client->nbread($error);
            if ($response !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response);
        $this->assertIsString($response);
    }

    public function testNbreadUnicodeMessage(): void
    {
        $client = $this->createClient(false);
        $message = "Hello 世界 🌍";
        $client->write($message);
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $response = $client->nbread($error);
            if ($response !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response);
    }

    public function testNbreadBinaryData(): void
    {
        $client = $this->createClient(false);
        $message = "\x00\x01\x02\x03\xFF\xFE\xFD";
        $client->write($message, true, true);
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $response = $client->nbread($error);
            if ($response !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response);
    }

    public function testNbreadLargeMessage(): void
    {
        $client = $this->createClient(false);
        $message = str_repeat("a", 1000);
        $client->write($message);
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $response = $client->nbread($error);
            if ($response !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response);
    }

    public function testMultipleReadCalls(): void
    {
        $client = $this->createClient(true);
        $client->write("first");
        $response1 = $client->read($error);
        $client->write("second");
        $response2 = $client->read($error);
        $client->write("third");
        $response3 = $client->read($error);
        $this->assertNotEmpty($response1);
        $this->assertNotEmpty($response2);
        $this->assertNotEmpty($response3);
    }

    public function testMultipleNbreadCalls(): void
    {
        $client = $this->createClient(false);
        $client->write("first");
        $response1 = '';
        for ($i = 0; $i < 20; $i++) {
            $response1 = $client->nbread($error);
            if ($response1 !== '') {
                break;
            }
            usleep(50000);
        }
        $client->write("second");
        $response2 = '';
        for ($i = 0; $i < 20; $i++) {
            $response2 = $client->nbread($error);
            if ($response2 !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response1);
        $this->assertNotEmpty($response2);
    }

    public function testReadEmptyMessage(): void
    {
        $client = $this->createClient(true);
        $message = "";
        $client->write($message);
        $response = $client->read($error);
        $this->assertNotEmpty($response);
    }

    public function testNbreadEmptyMessage(): void
    {
        $client = $this->createClient(false);
        $message = "";
        $client->write($message);
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $response = $client->nbread($error);
            if ($response !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response);
    }

    public function testReadRandomLengthMessage(): void
    {
        $client = $this->createClient(true);
        $length = rand(5, 25);
        $message = substr(str_repeat("abcdefghijklmnopqrstuvwxyz", ceil($length / 26)), 0, $length);
        $client->write($message);
        $response = $client->read($error);
        $this->assertNotEmpty($response);
        $this->assertEquals($length, strlen($response));
    }

    public function testNbreadRandomLengthMessage(): void
    {
        $client = $this->createClient(false);
        $length = rand(5, 25);
        $message = substr(str_repeat("abcdefghijklmnopqrstuvwxyz", ceil($length / 26)), 0, $length);
        $client->write($message);
        $response = '';
        for ($i = 0; $i < 20; $i++) {
            $response = $client->nbread($error);
            if ($response !== '') {
                break;
            }
            usleep(50000);
        }
        $this->assertNotEmpty($response);
        $this->assertEquals($length, strlen($response));
    }
}
