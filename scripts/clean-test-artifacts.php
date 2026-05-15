<?php

declare(strict_types=1);

$root = dirname(__DIR__);

foreach (['*.html', '*.mjml.php'] as $pattern) {
    foreach (glob($root.'/'.$pattern) ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
            echo "Removed {$path}\n";
        }
    }
}

foreach (['/tests/storage', '/storage'] as $relativeStorage) {
    $storage = $root.$relativeStorage;

    if (! is_dir($storage)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->getFilename() === '.gitignore') {
            continue;
        }

        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    echo "Cleared {$relativeStorage}/\n";
}

mkdir($root.'/tests/storage/framework/views', 0777, true);

echo "Done.\n";
