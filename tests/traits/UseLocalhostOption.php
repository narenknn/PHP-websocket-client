<?php

namespace Paragi\PhpWebsocket\tests\traits;

trait UseLocalhostOption
{
    private static ?bool $useLocalhost = null;

    private static function useLocalhost(): bool
    {
        if (self::$useLocalhost === null) {
            self::$useLocalhost = getenv('USE_LOCALHOST') !== false || getenv('USE_LOCALHOST') === '1';
            if (!self::$useLocalhost) {
                $options = getopt('', ['use-localhost']);
                self::$useLocalhost = isset($options['use-localhost']);
            }
        }

        return self::$useLocalhost;
    }
}
