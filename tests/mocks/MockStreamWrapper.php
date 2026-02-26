<?php

namespace Paragi\PhpWebsocket\tests\mocks;

class MockStreamWrapper
{
    public $context;
    private NbreadMockConnection $mockConn;
    private int $position = 0;
    private bool $connected = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->mockConn = new NbreadMockConnection();
        $this->connected = true;
        $this->position = 0;
        $opened_path = $path;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        if (!$this->connected) {
            return false;
        }

        return $this->mockConn->fread($count);
    }

    public function stream_write(string $data): int|false
    {
        if (!$this->connected) {
            return false;
        }
        return $this->mockConn->fwrite($data);
    }

    public function stream_eof(): bool
    {
        if (!$this->connected || !$this->mockConn) {
            return true;
        }
        return $this->mockConn->getReadPosition() >= strlen($this->mockConn->getReadData());
    }

    public function stream_close(): void
    {
        $this->connected = false;
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return true;
    }

    public function stream_cast(int $cast_as, bool &$can_read, bool &$can_write)
    {
        $can_read = true;
        $can_write = true;
        return false;
    }

    public static function register(): bool
    {
        return stream_wrapper_register('mock', MockStreamWrapper::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister('mock');
    }

    public function setMockConnection(NbreadMockConnection $conn): void
    {
        $this->mockConn = $conn;
    }

    public function getMockConnection(): NbreadMockConnection
    {
        return $this->mockConn;
    }
}

class MockStreamWrapperRegistry
{
    private static ?MockStreamWrapper $currentWrapper = null;

    public static function createConnection(string $data = ''): resource
    {
        $wrapper = new MockStreamWrapper();
        $wrapper->stream_open('mock://test', 'r+', 0, $path);
        
        if ($data !== '') {
            $conn = $wrapper->getMockConnection();
            $conn->setReadData($data);
        }
        
        self::$currentWrapper = $wrapper;
        
        return fopen('mock://test', 'r+');
    }

    public static function getCurrentWrapper(): ?MockStreamWrapper
    {
        return self::$currentWrapper;
    }
}
