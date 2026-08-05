<?php

namespace App\Platform\Modules\Runtime;

class ModuleRuntimeAutoloader
{
    /** @var array<string, true> */
    private static array $registered = [];

    /**
     * Register module PSR-4 prefixes before Laravel registers package providers.
     *
     * This intentionally uses native filesystem functions because bootstrap/app.php
     * calls it before the service container and facades exist.
     */
    public static function registerInstalledModules(?string $modulesPath = null): void
    {
        $modulesPath ??= dirname(__DIR__, 4).'/Modules';

        if (! is_dir($modulesPath)) {
            return;
        }

        $directories = glob(rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $name = basename($directory);

            if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1 && is_file($directory.'/module.json')) {
                self::register($name, $modulesPath);
            }
        }
    }

    public static function register(string $name, ?string $modulesPath = null): void
    {
        $modulesPath ??= dirname(__DIR__, 4).'/Modules';
        $prefix = "Modules\\{$name}\\";
        $base = rtrim($modulesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR;
        $registration = $prefix.$base;

        if (isset(self::$registered[$registration])) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($prefix, $base): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            if ($relative === false || str_contains($relative, '..')) {
                return;
            }

            $path = $base.str_replace('\\', '/', $relative).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        });

        self::$registered[$registration] = true;
    }
}
