<?php

namespace Paragi\PhpWebsocket\tests\unit;

require_once __DIR__ . '/../mocks/StreamConfig.php';
require_once __DIR__ . '/../mocks/EchoStreamWrapper.php';
require_once __DIR__ . '/../mocks/MockClient.php';

use Paragi\PhpWebsocket\tests\mocks\EchoStreamWrapper;
use Paragi\PhpWebsocket\tests\mocks\StreamConfig;
use PHPUnit\Framework\TestCase;

abstract class WebSocketTestCase extends TestCase
{
    protected StreamConfig $config;
    protected bool $wrapperRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();
        StreamConfig::reset();
        $this->config = StreamConfig::getInstance();
        
        if (!$this->wrapperRegistered) {
            $this->wrapperRegistered = EchoStreamWrapper::register();
        }
        
        $this->config->echoMode = false;
    }

    protected function tearDown(): void
    {
        if ($this->wrapperRegistered) {
            EchoStreamWrapper::unregister();
            $this->wrapperRegistered = false;
        }
        StreamConfig::reset();
        parent::tearDown();
    }

    protected function createClient(string $host = '127.0.0.1', int $port = 9999): \Paragi\PhpWebsocket\Client
    {
        $key = base64_encode(random_bytes(16));
        $this->config->setValidHandshake($key);
        
        return new \Paragi\PhpWebsocket\Client($host, $port, '', $error, 10, false, false, '/');
    }
}
