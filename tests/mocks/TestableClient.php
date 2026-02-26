<?php

namespace Paragi\PhpWebsocket\tests\mocks;

use Paragi\PhpWebsocket\Client;

class TestableClient extends Client
{
    private static ?\PHPUnit\Framework\MockObject\MockObject $mockConnection = null;
    private static array $writtenData = [];

    public function __construct(
        string $host = '',
        int $port = 80,
        $headers = '',
        &$error_string = '',
        int $timeout = 10,
        bool $ssl = false,
        bool $persistant = false,
        string $path = '/',
        $context = null,
        bool $blocking = true
    ) {
        $conn = @fopen('wsmock://test', 'r+');
        
        if ($conn === false) {
            throw new \Paragi\PhpWebsocket\ConnectionException("Unable to connect");
        }
        
        $buffer = '';
        while (strpos($buffer, "\r\n\r\n") === false) {
            $chunk = fread($conn, 1);
            if ($chunk === false || $chunk === '') break;
            $buffer .= $chunk;
        }
        
        $this->connection = $conn;
        $this->active = true;
        $this->config = [
            'blocking' => $blocking,
            'nb' => [
                'state' => 0,
                'data' => '',
                'dlen' => 0,
                'opcode' => 0,
                'final' => 0,
                'masked' => false,
                'mask' => '',
                'payload_len' => 0,
            ]
        ];
    }

    public static function getWrittenData(): array
    {
        return self::$writtenData;
    }

    public static function clearWrittenData(): void
    {
        self::$writtenData = [];
    }

    public function setConnection($conn): void
    {
        $this->connection = $conn;
    }

    public function getNbState(): int
    {
        return $this->config['nb']['state'] ?? 0;
    }

    public function setNbState(int $state): void
    {
        $this->config['nb']['state'] = $state;
    }

    public function getNbData(): string
    {
        return $this->config['nb']['data'] ?? '';
    }

    public function setNbData(string $data): void
    {
        $this->config['nb']['data'] = $data;
    }

    public function getNbDlen(): int
    {
        return $this->config['nb']['dlen'] ?? 0;
    }

    public function setNbDlen(int $dlen): void
    {
        $this->config['nb']['dlen'] = $dlen;
    }

    public function getNbPayloadLen(): int
    {
        return $this->config['nb']['payload_len'] ?? 0;
    }

    public function setNbPayloadLen(int $len): void
    {
        $this->config['nb']['payload_len'] = $len;
    }

    public function getNbMasked(): bool
    {
        return $this->config['nb']['masked'] ?? false;
    }

    public function setNbMasked(bool $masked): void
    {
        $this->config['nb']['masked'] = $masked;
    }

    public function getNbMask(): string
    {
        return $this->config['nb']['mask'] ?? '';
    }

    public function setNbMask(string $mask): void
    {
        $this->config['nb']['mask'] = $mask;
    }

    public function getNbOpcode(): int
    {
        return $this->config['nb']['opcode'] ?? 0;
    }

    public function setNbOpcode(int $opcode): void
    {
        $this->config['nb']['opcode'] = $opcode;
    }

    public function getNbFinal(): int
    {
        return $this->config['nb']['final'] ?? 0;
    }

    public function setNbFinal(int $final): void
    {
        $this->config['nb']['final'] = $final;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public static function getStreamWrapper(): ControlledStreamWrapper
    {
        return ControlledStreamWrapper::getInstance();
    }
}
