<?php

namespace Paragi\PhpWebsocket\tests\mocks;

use Paragi\PhpWebsocket\Client;
use Paragi\PhpWebsocket\ConnectionException;

class MockClientBuilder
{
    private string $host = '127.0.0.1';
    private int $port = 9999;
    private string $handshakeResponse = '';
    private bool $shouldFailConnect = false;
    private string $connectError = '';
    private int $connectErrno = 0;
    private array $readResponses = [];
    private int $readIndex = 0;
    private bool $echoWrites = true;
    private array $writeHistory = [];

    public function withHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function withPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function withValidHandshake(string $key = null): self
    {
        $key = $key ?? base64_encode(random_bytes(16));
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $this->handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: $accept\r\n" .
            "\r\n";
        return $this;
    }

    public function withInvalidHandshake(int $statusCode = 400): self
    {
        $this->handshakeResponse = "HTTP/1.1 $statusCode Bad Request\r\n" .
            "Content-Type: text/plain\r\n" .
            "Connection: close\r\n" .
            "\r\n" .
            "Not a WebSocket server";
        return $this;
    }

    public function withMissingKeyHandshake(): self
    {
        $this->handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "\r\n";
        return $this;
    }

    public function withConnectFailure(string $error, int $errno = 0): self
    {
        $this->shouldFailConnect = true;
        $this->connectError = $error;
        $this->connectErrno = $errno;
        return $this;
    }

    public function withReadResponse(string $data): self
    {
        $this->readResponses[] = $data;
        return $this;
    }

    public function withEchoWrites(bool $echo = true): self
    {
        $this->echoWrites = $echo;
        return $this;
    }

    public function getWriteHistory(): array
    {
        return $this->writeHistory;
    }

    public function build(): MockClient
    {
        return new MockClient(
            $this->host,
            $this->handshakeResponse,
            $this->shouldFailConnect,
            $this->connectError,
            $this->connectErrno,
            $this->readResponses,
            $this->echoWrites
        );
    }
}

class MockClient
{
    private $connection;
    private array $readResponses;
    private int $readIndex = 0;
    private bool $echoWrites;
    private array $writeHistory = [];
    private bool $handshakeDone = false;

    public function __construct(
        string $host,
        string $handshakeResponse,
        bool $shouldFail,
        string $connectError,
        int $connectErrno,
        array $readResponses,
        bool $echoWrites
    ) {
        $this->readResponses = $readResponses;
        $this->echoWrites = $echoWrites;
        
        if ($shouldFail) {
            throw new ConnectionException("Unable to connect: $connectError ($connectErrno)");
        }

        if (stripos($handshakeResponse, ' 101 ') === false) {
            throw new ConnectionException("Server did not accept to upgrade connection to websocket." . $handshakeResponse);
        }

        if (stripos($handshakeResponse, 'Sec-WebSocket-Accept: ') === false) {
            throw new ConnectionException("Server did not accept to upgrade connection to websocket." . $handshakeResponse);
        }

        $this->connection = fopen('php://memory', 'r+');
        fwrite($this->connection, $handshakeResponse);
        rewind($this->connection);
        $this->handshakeDone = true;
    }

    public function write($data, bool $final = true, bool $binary = true): int
    {
        $opcode = $binary ? 0x02 : 0x01;
        if (!$final) {
            $opcode = 0x00;
        }
        
        $firstByte = ($final ? 0x80 : 0) | $opcode;
        
        $payload = $data;
        
        $payloadLen = strlen($payload);
        if ($payloadLen < 126) {
            $header = chr($firstByte) . chr(0x80 | $payloadLen);
        } elseif ($payloadLen <= 0xFFFF) {
            $header = chr($firstByte) . chr(0x80 | 126) . pack('n', $payloadLen);
        } else {
            $header = chr($firstByte) . chr(0x80 | 127) . pack('N', 0) . pack('N', $payloadLen);
        }
        
        $mask = pack('N', rand(1, 0x7FFFFFFF));
        $header .= $mask;
        
        $maskedPayload = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $maskedPayload .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        
        $encodedFrame = $header . $maskedPayload;
        $this->writeHistory[] = $encodedFrame;
        
        return fwrite($this->connection, $encodedFrame);
    }

    public function read(&$error_string = null)
    {
        if (!empty($this->readResponses)) {
            if ($this->readIndex < count($this->readResponses)) {
                $response = $this->readResponses[$this->readIndex++];
                fwrite($this->connection, $response);
                rewind($this->connection);
            }
        } elseif ($this->echoWrites && !empty($this->writeHistory)) {
            $lastWrite = end($this->writeHistory);
            $echoFrame = $this->createEchoFrame($lastWrite);
            fwrite($this->connection, $echoFrame);
            rewind($this->connection);
        }
        
        return $this->readFrame($error_string);
    }

    private function createEchoFrame(string $data): string
    {
        $payload = $this->extractPayload($data);
        $payloadLen = strlen($payload);
        
        $firstByte = 0x88;
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

    private function readFrame(&$error_string = null): string
    {
        $data = "";

        do {
            $header = fread($this->connection, 2);
            if (!$header) {
                $error_string = "Reading header from websocket failed.";
                throw new ConnectionException($error_string);
            }

            $opcode = ord($header[0]) & 0x0F;
            $final = ord($header[0]) & 0x80;
            $masked = ord($header[1]) & 0x80;
            $payload_len = ord($header[1]) & 0x7F;

            $ext_len = 0;
            if ($payload_len >= 0x7E) {
                $ext_len = 2;
                if ($payload_len == 0x7F) {
                    $ext_len = 8;
                }
                $header = fread($this->connection, $ext_len);
                if (!$header) {
                    $error_string = "Reading header extension from websocket failed.";
                    throw new ConnectionException($error_string);
                }

                $payload_len = 0;
                for ($i = 0; $i < $ext_len; $i++) {
                    $payload_len += ord($header[$i]) << ($ext_len - $i - 1) * 8;
                }
            }

            if ($masked) {
                $mask = fread($this->connection, 4);
                if (!$mask) {
                    $error_string = "Reading header mask from websocket failed.";
                    throw new ConnectionException($error_string);
                }
            }

            $frame_data = '';
            while ($payload_len > 0) {
                $frame = fread($this->connection, $payload_len);
                if (!$frame) {
                    $error_string = "Reading payload from websocket failed.";
                    throw new ConnectionException($error_string);
                }
                $payload_len -= strlen($frame);
                $frame_data .= $frame;
            }

            if ($opcode == 9) {
                fwrite($this->connection, chr(0x8A) . chr(0x80) . pack("N", rand(1, 0x7FFFFFFF)));
                continue;
            } elseif ($opcode == 8) {
                fclose($this->connection);
            } elseif ($opcode < 3) {
                $data_len = strlen($frame_data);
                if ($masked) {
                    for ($i = 0; $i < $data_len; $i++) {
                        $data .= $frame_data[$i] ^ $mask[$i % 4];
                    }
                } else {
                    $data .= $frame_data;
                }
            } else {
                continue;
            }
        } while (!$final);

        return $data;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getWriteHistory(): array
    {
        return $this->writeHistory;
    }
}
