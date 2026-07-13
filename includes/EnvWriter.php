<?php

class EnvWriter
{
    public static function set(string $key, string $value): void
    {
        $path = __DIR__ . '/../.env';
        if (!file_exists($path)) {
            file_put_contents($path, "$key=$value\n");
            return;
        }
        $content = file_get_contents($path);
        $lines = explode("\n", $content);
        $found = false;
        foreach ($lines as &$line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) continue;
            if (str_starts_with($trimmed, "$key=")) {
                $line = "$key=$value";
                $found = true;
                break;
            }
        }
        unset($line);
        if (!$found) {
            $lines[] = "$key=$value";
        }
        file_put_contents($path, implode("\n", $lines));
    }

    public static function get(string $key): string
    {
        $path = __DIR__ . '/../.env';
        if (!file_exists($path)) return '';
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                if (trim($k) === $key) return trim(trim($v), '"\'');
            }
        }
        return '';
    }
}
