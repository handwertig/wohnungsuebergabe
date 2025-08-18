<?php
declare(strict_types=1);

namespace App;

final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        Auth::start();
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string,message:string}> */
    public static function pull(): array
    {
        Auth::start();
        $msgs = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return $msgs;
    }
}
