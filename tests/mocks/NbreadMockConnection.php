<?php

namespace Paragi\PhpWebsocket\tests\mocks;

class NbreadMockConnection
{
    private string $readData = '';
    private int $readPosition = 0;
    private int $chunkSize = -1;
    private bool $noDataMode = false;
    private array $writtenData = [];
    private bool $isClosed = false;

    public function setReadData(string $data): void
    {
        $this->readData = $data;
        $this->readPosition = 0;
        $this->noDataMode = false;
    }

    public function setChunkSize(int $size): void
    {
        $this->chunkSize = $size;
    }

    public function simulateNoData(): void
    {
        $this->noDataMode = true;
    }

    public function simulateHasData(): void
    {
        $this->noDataMode = false;
    }

    public function fread(int $length)
    {
        if ($this->isClosed) {
            return false;
        }

        if ($this->noDataMode) {
            return '';
        }

        if ($this->readPosition >= strlen($this->readData)) {
            return '';
        }

        $remaining = strlen($this->readData) - $this->readPosition;
        $toRead = ($this->chunkSize > 0 && $this->chunkSize < $length) 
            ? min($this->chunkSize, $remaining) 
            : min($length, $remaining);

        $data = substr($this->readData, $this->readPosition, $toRead);
        $this->readPosition += $toRead;

        return $data;
    }

    public function fwrite(string $data): int
    {
        if ($this->isClosed) {
            return false;
        }
        $this->writtenData[] = $data;
        return strlen($data);
    }

    public function getWrittenData(): array
    {
        return $this->writtenData;
    }

    public function close(): void
    {
        $this->isClosed = true;
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    public function getReadPosition(): int
    {
        return $this->readPosition;
    }

    public function rewind(): void
    {
        $this->readPosition = 0;
    }
}
