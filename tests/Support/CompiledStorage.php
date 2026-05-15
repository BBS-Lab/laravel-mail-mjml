<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Tests\Support;

use Illuminate\Support\Facades\File;

class CompiledStorage
{
    public static function root(): string
    {
        return dirname(__DIR__).'/storage';
    }

    public static function viewsPath(): string
    {
        return self::root().'/framework/views';
    }

    public static function ensureExists(string $directory): string
    {
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    public static function wipe(?string $directory = null): void
    {
        $directory ??= self::viewsPath();

        if (! is_dir($directory)) {
            return;
        }

        foreach (File::allFiles($directory) as $file) {
            File::delete($file->getPathname());
        }
    }

    /**
     * Remove compile artifacts accidentally written at the package root.
     */
    public static function wipePackageRootArtifacts(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['*.html', '*.mjml.php'] as $pattern) {
            foreach (glob($root.'/'.$pattern) ?: [] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
