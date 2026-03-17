<?php

class EnvParser
{
    public static function parse(): array
    {
        $envList = [];
        $envPath = '/app/waterbot/.env';
        if (!is_file($envPath)) {
            $envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        }

        $text = file_get_contents($envPath);
        $lines = explode("\n", trim($text));

        foreach ($lines as $line) {
            if (strpos($line, '=') > 0) {
                $parts = explode('=', $line);
                $envList[$parts[0]] = $parts[1];
            }
        }

        return $envList;
    }
}
