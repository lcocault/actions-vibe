<?php

declare(strict_types=1);

namespace App;

/**
 * Configuration lue depuis les variables d'environnement
 * (et, à défaut, depuis un fichier .env à la racine du projet).
 */
final class Config
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $envFile): void
    {
        self::$values = [];
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                self::$values[trim($key)] = trim($value, " \t\"'");
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return self::$values[$key] ?? $default;
    }
}
