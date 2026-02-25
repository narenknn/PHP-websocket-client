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
        $context = null
    ) {
        StreamConfig::getInstance()->host = $host;
        StreamConfig::getInstance()->port = $port;
        
        $key = base64_encode(random_bytes(16));
        StreamConfig::getInstance()->setValidHandshake($key);
        
        try {
            parent::__construct($host, $port, $headers, $error_string, $timeout, $ssl, $persistant, $path, $context);
        } catch (\Paragi\PhpWebsocket\ConnectionException $e) {
            throw $e;
        }
    }

    public static function getWrittenData(): array
    {
        return self::$writtenData;
    }

    public static function clearWrittenData(): void
    {
        self::$writtenData = [];
    }
}
