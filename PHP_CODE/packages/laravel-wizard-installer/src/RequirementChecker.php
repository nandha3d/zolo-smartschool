<?php

namespace dacoto\LaravelWizardInstaller;

/**
 * Resolves the installer's requirement checks.
 *
 * These used to be closures stored directly in config/installer.php. A closure in the
 * config array makes the whole array unserialisable, so `php artisan config:cache`
 * failed with "Call to undefined method Closure::__set_state()" - meaning the
 * application could never be config-cached in production. The checks are now plain
 * strings in config and are interpreted here.
 *
 * Supported specs:
 *   php:8              PHP major version is greater than the given value
 *   extension:curl     the named PHP extension is loaded
 *   writable:storage   the path, relative to the project root, exists and is writable
 */
class RequirementChecker
{
    public static function passes(string $spec): bool
    {
        [$type, $argument] = array_pad(explode(':', $spec, 2), 2, '');

        return match ($type) {
            'php'       => version_compare(PHP_VERSION, $argument, '>'),
            'extension' => extension_loaded($argument),
            'writable'  => self::pathIsWritable($argument),
            default     => false,
        };
    }

    /**
     * is_writable() rather than comparing File::chmod() against 755: the numeric
     * comparison was meaningless on Windows and told us nothing about whether the
     * process could actually write to the directory.
     */
    private static function pathIsWritable(string $relativePath): bool
    {
        $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        return is_dir($path) && is_writable($path);
    }
}
