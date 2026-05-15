<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use BBSLab\LaravelMjml\Tests\MjmlE2E\MjmlE2eTestCase;

uses(MjmlE2eTestCase::class);

covers(MjmlCompiler::class, MjmlIncludeResolver::class);

it('resolves the compiler from the container with the real mjml binary', function (): void {
    $compiler = app(MjmlCompiler::class, [
        'mjml' => view('mail.simple', ['name' => 'Container', 'footer' => 'DI']),
        'data' => ['name' => 'Container', 'footer' => 'DI'],
    ]);

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Hello Container')
        ->not->toContain('data-mjml-compiled');
});

it('compiles raw mjml strings with includes using a custom base path', function (): void {
    $basePath = __DIR__.'/../fixtures/views/mail';

    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-include path="mjml/footer.mjml" /></mj-body></mjml>',
        ['footer' => 'Raw string footer'],
        basePath: $basePath,
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toContain('Raw string footer')
        ->toContain('<table')
        ->not->toContain('<mj-include');
});

it('renders config and trans helpers into real html output', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.backend.contract-style', [
            'greeting' => 'Hi',
            'showSignature' => true,
            'signature' => 'Signed',
        ]),
        [
            'greeting' => 'Hi',
            'showSignature' => true,
            'signature' => 'Signed',
        ],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toContain('The Acme team')
        ->toContain('Acme')
        ->toContain('alt="Acme"')
        ->toMatch('/<img[^>]+src=["\'][^"\']*\/images\/email-footer\.png["\']/i');
});

it('renders unescaped blade html inside mj-text', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.with-unescaped', [
            'bodyHtml' => '<strong>Bold line</strong> and plain',
        ]),
        ['bodyHtml' => '<strong>Bold line</strong> and plain'],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toContain('<strong>Bold line</strong>')
        ->toContain('and plain')
        ->not->toContain('{!!')
        ->not->toContain('&lt;strong&gt;');
});

it('produces distinct html when mjml variables change between compilations', function (): void {
    $first = (new MjmlCompiler(
        '<mjml><mj-body><mj-text>{{ $label }}</mj-text></mj-body></mjml>',
        ['label' => 'First run'],
    ))->renderHtml()->toHtml();

    $second = (new MjmlCompiler(
        '<mjml><mj-body><mj-text>{{ $label }}</mj-text></mj-body></mjml>',
        ['label' => 'Second run'],
    ))->renderHtml()->toHtml();

    expect($first)->toContain('First run')
        ->and($second)->toContain('Second run')
        ->and($first)->not->toContain('Second run');
});

it('includes the mjml config file path in the cli command for blade views', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'CLI', 'footer' => 'Path']),
        ['name' => 'CLI', 'footer' => 'Path'],
    );

    $compiler->renderHtml();

    expect($compiler->buildCommandLine())
        ->toContain('--config.filePath=')
        ->toContain('mail');
});
