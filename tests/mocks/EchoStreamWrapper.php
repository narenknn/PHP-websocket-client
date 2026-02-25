<?php

namespace Paragi\PhpWebsocket\tests\mocks;

class EchoStreamWrapper
{
    private $context;
    private StreamConfig $config;
    private bool $connected = false;
    private bool $handshakeDone = false;
    private string $receivedData = '';
    private string $uri = '';
    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->config = StreamConfig::getInstance();
        $this->uri = $path;
        $parsed = parse_url($path);
        
        $this->config->host = $parsed['host'] ?? '127.0.0.1';
        $this->config->port = $parsed['port'] ?? 80;
        $this->config->path = $parsed['path'] ?? '/';

        if ($this->config->shouldFail) {
            return false;
        }

        if ($this->config->connectDelay > 0) {
            usleep($this->config->connectDelay * 1000);
        }

        $this->connected = true;
        $opened_path = $path;
        return true;
    }

    public function stream_read(int $count): string
    {
        if (!$this->connected) {
            return false;
        }

        if ($this->config->readDelay > 0) {
            usleep($this->config->readDelay * 1000);
        }

        if (!$this->handshakeDone) {
            $response = $this->config->handshakeResponse ?? $this->generateDefaultHandshake();
            $this->handshakeDone = true;
            $this->receivedData = $response;
            $this->position = 0;
        }

        if ($this->config->closeAfterFirstRead && strlen($this->receivedData) > 0) {
            $data = substr($this->receivedData, $this->position, $count);
            $this->position += strlen($data);
            return $data;
        }

        if ($this->config->echoMode && $this->handshakeDone && !empty($this->config->writeHistory)) {
            $lastWrite = end($this->config->writeHistory);
            $echoFrame = $this->createEchoFrame($lastWrite);
            $this->receivedData = $echoFrame;
            $this->config->clearHistory();
            $this->position = 0;
        }

        $data = substr($this->receivedData, $this->position, $count);
        $this->position += strlen($data);
        
        return $data;
    }

    public function stream_write(string $data): int
    {
        if (!$this->connected) {
            return false;
        }

        if ($this->config->writeDelay > 0) {
            usleep($this->config->writeDelay * 1000);
        }

        $this->config->recordWrittenData($data);
        return strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->receivedData);
    }

    public function stream_close(): void
    {
        $this->connected = false;
        $this->handshakeDone = false;
        $this->receivedData = '';
        $this->position = 0;
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        if ($option === STREAM_OPTION_BLOCKING) {
            return true;
        }
        if ($option === STREAM_OPTION_READ_TIMEOUT) {
            return true;
        }
        return false;
    }

    public function stream_cast(int $cast_as, bool &$can_read, bool &$can_write)
    {
        $can_read = true;
        $can_write = true;
        return false;
    }

    public function url_stat(string $path, int $flags): array
    {
        return [];
    }

    private function generateDefaultHandshake(): string
    {
        $key = base64_encode(random_bytes(16));
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        return "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: $accept\r\n" .
            "\r\n";
    }

    private function createEchoFrame(string $data): string
    {
        $firstByte = 0x88;
        $payload = $this->extractPayload($data);
        
        $payloadLen = strlen($payload);
        if ($payloadLen < 126) {
            $header = chr($firstByte) . chr($payloadLen);
        } elseif ($payloadLen < 0xFFFF) {
            $header = chr($firstByte) . chr(126) . pack('n', $payloadLen);
        } else {
            $header = chr($firstByte) . chr(127) . pack('N', 0) . pack('N', $payloadLen);
        }
        
        return $header . $payload;
    }

    private function extractPayload(string $frame): string
    {
        if (strlen($frame) < 2) {
            return '';
        }

        $secondByte = ord($frame[1]);
        $masked = ($secondByte & 0x80) !== 0;
        $payloadLen = $secondByte & 0x7F;

        $headerLen = 2;
        if ($payloadLen === 126) {
            $headerLen = 4;
        } elseif ($payloadLen === 127) {
            $headerLen = 10;
        }

        $maskKey = '';
        if ($masked && strlen($frame) >= $headerLen + 4) {
            $maskKey = substr($frame, $headerLen, 4);
            $headerLen += 4;
        }

        $payload = substr($frame, $headerLen);

        if ($masked && $maskKey !== '') {
            $unmasked = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $unmasked .= chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
            }
            return $unmasked;
        }

        return $payload;
    }

    public static function register(): bool
    {
        return stream_wrapper_register('ws', EchoStreamWrapper::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister('ws');
    }
}
