<?php

class EnvParser
{
    public static function parse(): array
    {
        $envList = [];
        $text = file_get_contents('/app/waterbot/.env');
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