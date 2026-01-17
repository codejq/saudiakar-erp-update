<?php
/**
 * Secrets Manager
 * Loads secrets from protected directory
 * Location: Y:\lib\storage\SecretsManager.php
 */

declare(strict_types=1);

class SecretsManager
{
    private static ?array $secrets = null;

    /**
     * Get secret value with dot notation
     * Example: get('ai.openrouter_api_key')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$secrets === null) {
            self::load();
        }

        $segments = explode('.', $key);
        $value = self::$secrets;

        foreach ($segments as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Load secrets from protected file
     */
    private static function load(): void
    {
        $secrets_file = STORAGE_PROTECTED . 'secrets.php';

        if (!file_exists($secrets_file)) {
            self::$secrets = [];
            return;
        }

        self::$secrets = require $secrets_file;
    }

    /**
     * Check if secret exists
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Set a secret value (runtime only, does not persist)
     */
    public static function set(string $key, mixed $value): void
    {
        if (self::$secrets === null) {
            self::load();
        }

        $segments = explode('.', $key);
        $current = &self::$secrets;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Get all secrets
     */
    public static function all(): array
    {
        if (self::$secrets === null) {
            self::load();
        }

        return self::$secrets;
    }
}
