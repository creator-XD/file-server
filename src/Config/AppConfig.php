<?php

namespace App\Config;

final class AppConfig
{
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function load(): self
    {
        $env = getenv('APP_ENV') ?: 'dev';

        $path = __DIR__ . '/../../config/' . $env . '.php';

        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: $path");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return array: $path");
        }

        return new self($config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->config;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }

            $value = $value[$part];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}