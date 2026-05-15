<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use BBSLab\LaravelMjml\Tests\Support\CompiledStorage;
use Illuminate\Support\Facades\File;

covers(MjmlCompiler::class, MjmlIncludeResolver::class);

beforeEach(function (): void {
    wipeCompiledStorage();
});

it('resolves blade variables in the root mjml view', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Claire', 'footer' => 'Footer line']),
        ['name' => 'Claire', 'footer' => 'Footer line'],
    );

    expectIncludeBladeResolved($compiler->renderHtml()->toHtml(), [
        'Hello Claire',
        'Footer line',
    ]);
});

it('resolves blade variables inside mj-include partials', function (): void {
    $resolver = new MjmlIncludeResolver;

    $resolved = resolveMjmlWithBlade(
        $resolver,
        '<mjml><mj-body><mj-include path="mjml/footer.mjml" /></mj-body></mjml>',
        __DIR__.'/../fixtures/views/mail',
        ['footer' => 'Signed, Acme'],
    );

    expectIncludeBladeResolved($resolved, [
        'Signed, Acme',
    ]);
});

it('resolves trans asset and config helpers inside includes', function (): void {
    $resolver = new MjmlIncludeResolver;

    $resolved = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="mjml/footer-rich.mjml" />',
        __DIR__.'/../fixtures/views/mail',
        [
            'showSignature' => true,
            'signature' => 'Mikael',
        ],
    );

    expectIncludeBladeResolved($resolved, [
        'The Acme team',
        'Mikael',
        '/images/email-footer.png',
        'Acme',
    ]);
});

it('resolves conditional blade inside includes', function (): void {
    $resolver = new MjmlIncludeResolver;

    $withSignature = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="mjml/footer-rich.mjml" />',
        __DIR__.'/../fixtures/views/mail',
        ['showSignature' => true, 'signature' => 'Visible'],
    );

    $withoutSignature = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="mjml/footer-rich.mjml" />',
        __DIR__.'/../fixtures/views/mail',
        ['showSignature' => false, 'signature' => 'Hidden'],
    );

    expect($withSignature)->toContain('Visible')
        ->and($withoutSignature)->not->toContain('Hidden')
        ->and($withoutSignature)->not->toContain('@if');
});

it('resolves nested mj-include partials with blade in each level', function (): void {
    $resolver = new MjmlIncludeResolver;

    $resolved = resolveMjmlWithBlade(
        $resolver,
        '<mjml><mj-body><mj-include path="mjml/header.mjml" /></mj-body></mjml>',
        __DIR__.'/../fixtures/views/mail',
        [
            'headline' => 'Weekly digest',
            'snippet' => 'Nested snippet text',
        ],
    );

    expectIncludeBladeResolved($resolved, [
        'Weekly digest',
        'Nested snippet text',
    ]);
});

it('resolves blade in nested views using relative mj-include paths like production apps', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.nested.notification', [
            'name' => 'Mikael',
            'footer' => 'Acme Inc',
        ]),
        ['name' => 'Mikael', 'footer' => 'Acme Inc'],
    );

    expectIncludeBladeResolved($compiler->renderHtml()->toHtml(), [
        'Greeting Mikael',
        'Acme Inc',
    ]);
});

it('resolves production-like contract mails end to end', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.backend.contract-style', [
            'greeting' => 'Bonjour',
            'showSignature' => true,
            'signature' => 'Acme support',
        ]),
        [
            'greeting' => 'Bonjour',
            'showSignature' => true,
            'signature' => 'Acme support',
        ],
    );

    expectIncludeBladeResolved($compiler->renderHtml()->toHtml(), [
        'Bonjour',
        'The Acme team',
        'Acme support',
        'Acme',
    ]);
});

it('resolves nested includes through the full compiler pipeline', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.with-header-chain', [
            'headline' => 'Title',
            'snippet' => 'Details',
            'showSignature' => false,
            'signature' => '',
        ]),
        [
            'headline' => 'Title',
            'snippet' => 'Details',
            'showSignature' => false,
            'signature' => '',
        ],
    );

    expectIncludeBladeResolved($compiler->renderHtml()->toHtml(), [
        'Title',
        'Details',
    ]);
});

it('resolves blade in raw mjml content strings', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Hi {{ $name }}</mj-text></mj-body></mjml>',
        ['name' => 'Raw string'],
    );

    expectIncludeBladeResolved($compiler->renderHtml()->toHtml(), [
        'Hi Raw string',
    ]);
});

it('supports single and double quoted mj-include paths', function (): void {
    $resolver = new MjmlIncludeResolver;

    $single = resolveMjmlWithBlade(
        $resolver,
        "<mj-include path='mjml/footer.mjml' />",
        __DIR__.'/../fixtures/views/mail',
        ['footer' => 'Single quotes'],
    );

    $double = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="mjml/footer.mjml" />',
        __DIR__.'/../fixtures/views/mail',
        ['footer' => 'Double quotes'],
    );

    expect($single)->toContain('Single quotes')
        ->and($double)->toContain('Double quotes');
});

it('does not resolve blade inside mj-include when preprocessing is disabled', function (): void {
    config()->set('mjml.process_includes_with_blade', false);

    $compiler = new MjmlCompiler(
        view('mail.backend.contract-style', [
            'greeting' => 'Bonjour',
            'showSignature' => true,
            'signature' => 'Hidden in include',
        ]),
        [
            'greeting' => 'Bonjour',
            'showSignature' => true,
            'signature' => 'Hidden in include',
        ],
    );

    $html = $compiler->renderHtml()->toHtml();

    expectBladeResolved($html, [
        'Hidden in include',
        'The Acme team',
    ], [
        'Bonjour',
    ]);

    expect($html)->toContain('<mj-include');
});

it('stores compiled output without duplicate slashes when view.compiled has a trailing slash', function (): void {
    $compiledDirectory = CompiledStorage::ensureExists(
        CompiledStorage::root().'/trailing-slash',
    );

    config()->set('view.compiled', $compiledDirectory.'///');
    wipeCompiledStorage($compiledDirectory);

    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Slash', 'footer' => 'Ok']),
        ['name' => 'Slash', 'footer' => 'Ok'],
    );

    $compiler->renderHtml();

    $compiledFiles = File::files($compiledDirectory);

    expect($compiledFiles)->not->toBeEmpty();
    expect($compiledFiles[0]->getPathname())
        ->toStartWith($compiledDirectory.DIRECTORY_SEPARATOR)
        ->not->toContain(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
});
