<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Tests\Support;

final class MjmlBinary
{
    public static function isAvailable(): bool
    {
        return self::resolve() !== null;
    }

    public static function resolve(): ?string
    {
        $override = getenv('MJML_PATH_TO_BINARY') ?: getenv('MJML_E2E_BINARY');

        if (is_string($override) && $override !== '' && is_file($override)) {
            return $override;
        }

        $packageRoot = dirname(__DIR__, 2);

        foreach (['mjml', 'mjml.cmd'] as $name) {
            $path = $packageRoot.'/node_modules/.bin/'.$name;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
