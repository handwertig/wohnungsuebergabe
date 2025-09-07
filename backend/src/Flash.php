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

    public static function display(): void
    {
        $messages = self::pull();
        foreach ($messages as $msg) {
            $type = match($msg['type']) {
                'error' => 'danger',
                'success' => 'success',
                'warning' => 'warning',
                default => 'info'
            };
            echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
                . htmlspecialchars($msg['message'])
                . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
                . '</div>';
        }
    }
}
