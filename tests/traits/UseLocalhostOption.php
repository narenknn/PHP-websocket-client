<?php

namespace Paragi\PhpWebsocket\tests\traits;

trait UseLocalhostOption
{
    private static ?bool $useLocalhost = null;

    private static function useLocalhost(): bool
    {
        if (self::$useLocalhost === null) {
            self::$useLocalhost = in_array('--use-localhost', $_SERVER['argv']);
        }

        return self::$useLocalhost;
    }
}
