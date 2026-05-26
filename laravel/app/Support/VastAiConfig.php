<?php

namespace App\Support;

use App\Models\AdminSetting;

class VastAiConfig
{
    public static function current(): array
    {
        return [
            'host' => trim((string) AdminSetting::get('vastai_ssh_host', env('VAST_HOST', '123.21.80.170'))),
            'port' => (int) AdminSetting::get('vastai_ssh_port', env('VAST_PORT', 51734)),
            'user' => trim((string) AdminSetting::get('vastai_ssh_user', env('VAST_USER', 'root'))),
        ];
    }

    public static function shellEnvFilePath(): string
    {
        return dirname(base_path()) . '/scripts/.vastai-tunnel.env';
    }

    public static function writeShellEnvFile(): void
    {
        $config = self::current();
        $path = self::shellEnvFilePath();

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $content = implode("\n", [
            '# Managed by Laravel admin settings',
            'VAST_HOST=' . self::escapeShellValue($config['host']),
            'VAST_PORT=' . (int) $config['port'],
            'VAST_USER=' . self::escapeShellValue($config['user']),
            '',
        ]);

        file_put_contents($path, $content, LOCK_EX);
    }

    private static function escapeShellValue(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }
}