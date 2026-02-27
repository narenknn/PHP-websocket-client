<?php

use Paragi\PhpWebsocket\Client;
use Paragi\PhpWebsocket\ConnectionException;
use Paragi\PhpWebsocket\tests\traits\UseLocalhostOption;
use PHPUnit\Framework\TestCase;

/**
 * Test for Client
 *
 * @author Trismegiste
 */
class ClientTest extends TestCase
{
    use UseLocalhostOption;

    const testServerDomain = '127.0.0.1';
    const testServerPort = 9999;
    const ECHO_SERVER = 'echo.websocket.org';
    const ECHO_PORT = 443;

    private function getServerDomain(): string
    {
        return self::useLocalhost() ? self::testServerDomain : self::ECHO_SERVER;
    }

    private function getServerPort(): int
    {
        return self::useLocalhost() ? self::testServerPort : self::ECHO_PORT;
    }

    public function testConnectToLocalEchoingServer()
    {
        try {
            $obj = new Client($this->getServerDomain(), $this->getServerPort());
            $this->assertInstanceOf(Client::class, $obj);
        } catch (\Paragi\PhpWebsocket\ConnectionException $e) {
            $this->assertTrue(false, 'Unable to connect to test server. Did you forget to launch the test server ?');
        }
    }

    public function getMessage(): array
    {
        return [
            ["hello server"],
            ["Here's a binary \x01\x02\x03"],
            ['So Long, and Thanks for All the Fish']
        ];
    }

    /**
     * @dataProvider getMessage
     */
    public function testExample(string $message)
    {
        $sut = new Client($this->getServerDomain(), $this->getServerPort(), '', $errstr, 3, false);
        $written = $sut->write($message);
        $this->assertNotFalse($written, 'Unable to write to ' . $this->getServerDomain());
        $response = $sut->read($errstr);
        $this->assertEquals($message, $response);
    }

    public function testUnknowHost()
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('getaddrinfo')->expectExceptionMessage('failed');
        new Client('yoloserver.unknown');
    }

    public function testNotAWebsocketServer()
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('upgrade connection');
        new Client('twitter.com');
    }

}
