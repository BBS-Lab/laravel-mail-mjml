<?php

declare(strict_types=1);

$tempRoot = dirname(__DIR__).'/vendor/pestphp/pest-plugin-mutate/.temp';

foreach (['mutations', 'pest-mutate-cache'] as $directory) {
    $path = $tempRoot.'/'.$directory;

    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        fwrite(STDERR, "Unable to create Pest Mutate temp directory: {$path}\n");

        exit(1);
    }
}
