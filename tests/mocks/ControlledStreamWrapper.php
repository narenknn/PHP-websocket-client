<?php

namespace Paragi\PhpWebsocket\tests\mocks;

class ControlledStreamWrapper
{
    public $context;
    private string $data = '';
    private int $position = 0;
    private int $chunkSize = -1;
    private bool $noData = false;
    private array $writtenData = [];

    private static ?ControlledStreamWrapper $instance = null;
    private static string $pendingData = '';
    private static int $pendingChunkSize = -1;
    private static bool $pendingNoData = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $opened_path = $path;
        self::$instance = $this;
        $this->data = self::$pendingData;
        $this->position = 0;
        $this->chunkSize = self::$pendingChunkSize;
        $this->noData = self::$pendingNoData;
        self::$pendingData = '';
        self::$pendingChunkSize = -1;
        self::$pendingNoData = false;
        return true;
    }

    public function stream_read(int $length): string|false
    {
        if ($this->noData) {
            return '';
        }

        if ($this->position >= strlen($this->data)) {
            return '';
        }

        $remaining = strlen($this->data) - $this->position;
        $toRead = ($this->chunkSize > 0 && $this->chunkSize < $length) 
            ? min($this->chunkSize, $remaining) 
            : min($length, $remaining);

        $result = substr($this->data, $this->position, $toRead);
        $this->position += strlen($result);
        return $result;
    }

    public function stream_write(string $data): int|false
    {
        $this->writtenData[] = $data;
        return strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_close(): void
    {
        $this->position = 0;
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

    public function url_stat(string $path, int $flags): array
    {
        return [];
    }

    public function setData(string $data): void
    {
        $this->data = $data;
        $this->position = 0;
    }

    public function setChunkSize(int $size): void
    {
        $this->chunkSize = $size;
    }

    public function setNoData(bool $noData): void
    {
        $this->noData = $noData;
    }

    public function getWrittenData(): array
    {
        return $this->writtenData;
    }

    public function clearWrittenData(): void
    {
        $this->writtenData = [];
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public static function register(): bool
    {
        return stream_wrapper_register('wsmock', ControlledStreamWrapper::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister('wsmock');
        self::$instance = null;
    }

    public static function getInstance(): ?ControlledStreamWrapper
    {
        return self::$instance;
    }

    public static function setPendingData(string $data): void
    {
        self::$pendingData = $data;
    }

    public static function setPendingChunkSize(int $size): void
    {
        self::$pendingChunkSize = $size;
    }

    public static function setPendingNoData(bool $noData): void
    {
        self::$pendingNoData = $noData;
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$pendingData = '';
        self::$pendingChunkSize = -1;
        self::$pendingNoData = false;
    }
}
