<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\LaravelMjmlServiceProvider;
use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;

covers(LaravelMjmlServiceProvider::class, MjmlCompiler::class);

it('registers mjml services in the container', function (): void {
    expect(app(MjmlIncludeResolver::class))->toBeInstanceOf(MjmlIncludeResolver::class);

    $compiler = app()->make(MjmlCompiler::class, [
        'mjml' => '<mjml><mj-body></mj-body></mjml>',
    ]);

    expect($compiler)->toBeInstanceOf(MjmlCompiler::class);
});

it('resolves the compiler with container defaults when optional parameters are omitted', function (): void {
    $compiler = app()->make(MjmlCompiler::class, [
        'mjml' => '<mjml><mj-body><mj-text>Defaults</mj-text></mj-body></mjml>',
    ]);

    expect($compiler->renderHtml()->toHtml())->toContain('Defaults');
});

it('resolves the compiler from the container with optional parameters', function (): void {
    $compiler = app()->make(MjmlCompiler::class, [
        'mjml' => '<mjml><mj-body><mj-text>Bound</mj-text></mj-body></mjml>',
        'data' => ['unused' => true],
        'basePath' => __DIR__.'/fixtures/views/mail',
    ]);

    expect($compiler->renderHtml()->toHtml())->toContain('Bound');
});

it('reuses the same include resolver instance', function (): void {
    $first = app(MjmlIncludeResolver::class);
    $second = app(MjmlIncludeResolver::class);

    expect($first)->toBe($second);
});
