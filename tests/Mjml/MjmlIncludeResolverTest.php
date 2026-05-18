<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;

covers(MjmlIncludeResolver::class);

it('inlines mj-include partials without evaluating blade', function (): void {
    $resolver = new MjmlIncludeResolver;

    $assembled = $resolver->resolve(
        '<mjml><mj-body><mj-include path="mjml/footer.mjml" /></mj-body></mjml>',
        __DIR__.'/../fixtures/views/mail',
    );

    expect($assembled)->toContain('{{ $footer }}')
        ->and($assembled)->not->toContain('<mj-include');
});

it('inlines mj-include partials and renders blade', function (): void {
    $resolver = new MjmlIncludeResolver;

    $baseDirectory = __DIR__.'/../fixtures/views/mail';
    $mjml = '<mjml><mj-body><mj-include path="mjml/footer.mjml" /></mj-body></mjml>';

    $result = resolveMjmlWithBlade($resolver, $mjml, $baseDirectory, ['footer' => 'Thanks']);

    expectBladeResolved($result, [
        '{{ $footer }}',
        '<mj-include',
    ], [
        'Thanks',
    ]);
});

it('resolves absolute include paths', function (): void {
    $resolver = new MjmlIncludeResolver;

    $footer = realpath(__DIR__.'/../fixtures/views/mail/mjml/footer.mjml');

    $result = resolveMjmlWithBlade(
        $resolver,
        '<mj-include path="'.$footer.'" />',
        __DIR__.'/../fixtures/views/mail',
        ['footer' => 'Absolute'],
    );

    expect($result)->toContain('Absolute');
});

it('resolves nested mj-include paths relative to the parent partial directory', function (): void {
    $resolver = new MjmlIncludeResolver;

    $result = resolveMjmlWithBlade(
        $resolver,
        '<mjml><mj-body><mj-include path="mjml/header.mjml" /></mj-body></mjml>',
        __DIR__.'/../fixtures/views/mail',
        [
            'headline' => 'Digest',
            'snippet' => 'From nested partial',
        ],
    );

    expectBladeResolved($result, [
        '<mj-include',
        '{{ $snippet }}',
    ], [
        'Digest',
        'From nested partial',
    ]);
});

it('throws when an include cannot be found', function (): void {
    $resolver = new MjmlIncludeResolver;

    $resolver->resolve(
        '<mj-include path="missing.mjml" />',
        __DIR__.'/../fixtures/views/mail',
    );
})->throws(RuntimeException::class, 'could not be found');
