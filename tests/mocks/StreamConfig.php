<?php

namespace Paragi\PhpWebsocket\tests\mocks;

class StreamConfig
{
    public ?string $host = null;
    public ?int $port = null;
    public string $path = '/';
    public array $headers = [];
    public int $errno = 0;
    public string $errstr = '';
    public bool $shouldFail = false;
    public int $connectDelay = 0;
    public int $readDelay = 0;
    public int $writeDelay = 0;
    public ?string $handshakeResponse = null;
    public ?string $readResponse = null;
    public array $writeHistory = [];
    public bool $echoMode = true;
    public bool $closeAfterFirstRead = false;
    public array $readQueue = [];
    private int $readQueueIndex = 0;

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = new self();
    }

    public function queueRead(string $data): void
    {
        $this->readQueue[] = $data;
    }

    public function getNextRead(): ?string
    {
        if ($this->readQueueIndex < count($this->readQueue)) {
            return $this->readQueue[$this->readQueueIndex++];
        }
        return $this->readResponse;
    }

    public function clearQueue(): void
    {
        $this->readQueue = [];
        $this->readQueueIndex = 0;
    }

    public function setValidHandshake(string $key): void
    {
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $this->handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: $accept\r\n" .
            "\r\n";
    }

    public function setInvalidHandshake(int $statusCode = 400): void
    {
        $this->handshakeResponse = "HTTP/1.1 $statusCode Bad Request\r\n" .
            "Content-Type: text/plain\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "Not a WebSocket server";
    }

    public function setMissingKeyHandshake(): void
    {
        $this->handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "\r\n";
    }

    public function recordWrittenData(string $data): void
    {
        $this->writeHistory[] = $data;
    }

    public function clearHistory(): void
    {
        $this->writeHistory = [];
    }
}
