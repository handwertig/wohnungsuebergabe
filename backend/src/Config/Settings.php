<?php
declare(strict_types=1);

namespace App\Config;

final class Settings
{
	public static function db(): array
	{
		return [
			'host' => getenv('DB_HOST') ?: 'db',
			'port' => (int) (getenv('DB_PORT') ?: '3306'),
			'name' => getenv('DB_NAME') ?: 'app',
			'user' => getenv('DB_USER') ?: 'app',
			'pass' => getenv('DB_PASS') ?: 'app',
		];
	}
}
