<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Mjml;

use RuntimeException;

class MjmlIncludeResolver
{
    protected const INCLUDE_PATTERN = '/<mj-include\s+[^>]*\bpath=(["\'])(.*?)\1[^>]*\/?>/i';

    /**
     * Inline MJML includes without evaluating Blade.
     *
     * Blade should run once on the assembled document (see {@see MjmlCompiler::prepareMjmlContent}).
     */
    public function resolve(string $mjml, string $baseDirectory): string
    {
        $previous = null;

        while ($previous !== $mjml) {
            $previous = $mjml;

            $mjml = preg_replace_callback(
                self::INCLUDE_PATTERN,
                fn (array $matches): string => $this->inlineInclude($matches[2], $baseDirectory),
                $mjml,
            ) ?? $mjml;
        }

        return $mjml;
    }

    protected function inlineInclude(string $relativePath, string $baseDirectory): string
    {
        $absolutePath = $this->resolvePath($relativePath, $baseDirectory);

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("MJML include [{$relativePath}] could not be found at [{$absolutePath}].");
        }

        $content = (string) file_get_contents($absolutePath);

        return $this->resolve($content, dirname($absolutePath));
    }

    protected function resolvePath(string $path, string $baseDirectory): string
    {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (str_starts_with($normalizedPath, DIRECTORY_SEPARATOR)) {
            return $normalizedPath;
        }

        $resolved = realpath($baseDirectory.DIRECTORY_SEPARATOR.$normalizedPath);

        if ($resolved !== false) {
            return $resolved;
        }

        return $baseDirectory.DIRECTORY_SEPARATOR.$normalizedPath;
    }
}
