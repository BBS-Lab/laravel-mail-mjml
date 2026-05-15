<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use Illuminate\Support\Facades\File;

covers(MjmlIncludeResolver::class);

it('resolves existing paths through realpath when available', function (): void {
    $resolver = new MjmlIncludeResolver;
    $baseDirectory = __DIR__.'/../fixtures/views/mail';
    $method = new ReflectionMethod($resolver, 'resolvePath');
    $method->setAccessible(true);

    $resolvedPath = $method->invoke($resolver, 'mjml/footer.mjml', $baseDirectory);

    expect($resolvedPath)->toBe(realpath($baseDirectory.'/mjml/footer.mjml'));
});

it('supports absolute include paths on disk', function (): void {
    $resolver = new MjmlIncludeResolver;
    $footer = realpath(__DIR__.'/../fixtures/views/mail/mjml/footer.mjml');

    $resolved = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="'.$footer.'" />',
        __DIR__.'/../fixtures/views/mail',
        ['footer' => 'Absolute footer'],
    );

    expect($resolved)->toContain('Absolute footer');
});

it('renders empty partial files without leaving blade placeholders', function (): void {
    $resolver = new MjmlIncludeResolver;
    $baseDirectory = __DIR__.'/../fixtures/views/mail/empty-partial';
    File::ensureDirectoryExists($baseDirectory);
    File::put($baseDirectory.'/empty.mjml', '');

    try {
        $resolved = $resolver->resolve(
            '<mj-include path="empty.mjml" />',
            $baseDirectory,
        );

        expect($resolved)->toBe('');
    } finally {
        File::deleteDirectory($baseDirectory);
    }
});

it('normalizes windows style separators in include paths', function (): void {
    $resolver = new MjmlIncludeResolver;

    $resolved = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="mjml\\footer.mjml" />',
        __DIR__.'/../fixtures/views/mail',
        ['footer' => 'Windows path'],
    );

    expect($resolved)->toContain('Windows path');
});

it('inlines multiple includes in separate passes', function (): void {
    $resolver = new MjmlIncludeResolver;

    $baseDirectory = __DIR__.'/../fixtures/views/mail/multi-include';
    File::ensureDirectoryExists($baseDirectory);
    File::put($baseDirectory.'/first.mjml', '<mj-text>{{ $first }}</mj-text>');
    File::put($baseDirectory.'/second.mjml', '<mj-text>{{ $second }}</mj-text>');

    try {
        $resolved = resolveMjmlWithBlade(
            $resolver,
            '<mj-include path="first.mjml" /><mj-include path="second.mjml" />',
            $baseDirectory,
            ['first' => 'One', 'second' => 'Two'],
        );

        expect($resolved)->toContain('One')
            ->and($resolved)->toContain('Two')
            ->and($resolved)->not->toContain('<mj-include');
    } finally {
        File::deleteDirectory($baseDirectory);
    }
});
