<?php

declare(strict_types=1);

namespace eseperio\verifactu\utils;

use Dotenv\Dotenv;

/**
 * Utility class for loading environment variables from .env file.
 */
class EnvLoader
{
    private static bool $loaded = false;

    /**
     * Load environment variables from .env file.
     * This method is idempotent - calling it multiple times will only load the .env file once.
     *
     * @param string $basePath Base path where the .env file is located (defaults to project root)
     * @return void
     */
    public static function load(string $basePath = null): void
    {
        if (self::$loaded) {
            return;
        }

        // If no path provided, use the project root directory
        if ($basePath === null) {
            $basePath = dirname(__DIR__, 2); // Go up two levels from src/utils
        }

        // Only load if .env file exists
        if (file_exists($basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->load();
            self::$loaded = true;
        }
    }

    /**
     * Get the value of an environment variable.
     *
     * @param string $name Name of the environment variable
     * @param mixed $default Default value if the environment variable is not set
     * @return mixed Value of the environment variable or default
     */
    public static function get(string $name, $default = null)
    {
        self::load();
        
        return $_ENV[$name] ?? $default;
    }

    /**
     * Get certificate path from environment variables.
     *
     * @return string|null Certificate path or null if not set
     */
    public static function getCertPath(): ?string
    {
        $path = self::get('VERIFACTU_CERT_PATH');
        if (empty($path)) {
            return null;
        }
        // Ensure it's a regular file; if it's a directory (common when host path missing), treat as not set
        if (!is_file($path)) {
            return null;
        }
        return $path;
    }

    /**
     * Get certificate password from environment variables.
     *
     * @return string|null Certificate password or null if not set
     */
    public static function getCertPassword(): ?string
    {
        return self::get('VERIFACTU_CERT_PASSWORD');
    }

    /**
     * Get certificate type from environment variables.
     *
     * @return string Certificate type (defaults to 'certificate')
     */
    public static function getCertType(): string
    {
        return self::get('VERIFACTU_CERT_TYPE', 'certificate');
    }

    /**
     * Get environment from environment variables.
     *
     * @return string Environment (defaults to 'sandbox')
     */
    public static function getEnvironment(): string
    {
        return self::get('VERIFACTU_ENVIRONMENT', 'sandbox');
    }

    /**
     * Check if all required environment variables for sandbox testing are set.
     *
     * @return bool True if all required variables are set, false otherwise
     */
    public static function hasSandboxConfig(): bool
    {
        self::load();
        
        return self::getCertPath() !== null && 
               self::getCertPassword() !== null && 
               self::getCertType() !== null &&
               self::getEnvironment() !== null;
    }
}
