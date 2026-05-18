<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use Illuminate\Support\Facades\File;

covers(MjmlIncludeResolver::class);

it('resolves relative paths when realpath is unavailable', function (): void {
    $resolver = new MjmlIncludeResolver;

    $baseDirectory = __DIR__.'/../fixtures/views/mail/not-realpath-parent';
    $targetDirectory = $baseDirectory.'/mjml';

    File::ensureDirectoryExists($targetDirectory);
    File::put($targetDirectory.'/snippet.mjml', '<mj-text>{{ $label }}</mj-text>');

    try {
        $resolved = resolveMjmlWithBlade(
            $resolver,
            '<mj-include path="mjml/snippet.mjml" />',
            $baseDirectory,
            ['label' => 'Fallback path'],
        );

        expectBladeResolved($resolved, [
            '{{ $label }}',
        ], [
            'Fallback path',
        ]);
    } finally {
        File::deleteDirectory($baseDirectory);
    }
});

it('requires includes to be readable files', function (): void {
    $resolver = new MjmlIncludeResolver;

    $baseDirectory = __DIR__.'/../fixtures/views/mail';

    $resolver->resolve(
        '<mj-include path="mjml" />',
        $baseDirectory,
    );
})->throws(RuntimeException::class, 'could not be found');
