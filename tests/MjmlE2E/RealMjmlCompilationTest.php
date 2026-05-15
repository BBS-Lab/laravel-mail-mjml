<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Concerns\BuildsMjmlMail;
use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Tests\MjmlE2E\MjmlE2eTestCase;
use BBSLab\LaravelMjml\Tests\Support\CompiledStorage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\File;

uses(MjmlE2eTestCase::class);

covers(MjmlCompiler::class, BuildsMjmlMail::class);

it('compiles a blade mjml view with the real mjml cli into responsive html', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Ada', 'footer' => 'Acme Inc']),
        ['name' => 'Ada', 'footer' => 'Acme Inc'],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('<table')
        ->toContain('Hello Ada')
        ->toContain('Acme Inc')
        ->not->toContain('<mj-text')
        ->not->toContain('<mj-include')
        ->not->toContain('{{ $')
        ->not->toContain('data-mjml-compiled');
});

it('compiles production-like contract mails with nested includes via the real mjml cli', function (): void {
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

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('<table')
        ->toContain('Bonjour')
        ->toContain('The Acme team')
        ->toContain('Acme support')
        ->toContain('/images/email-footer.png')
        ->not->toContain('<mj-section')
        ->not->toContain('{{ $')
        ->not->toContain('@if');
});

it('produces a plain text part from real compiled html', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text font-size="16px">Plain MJML body</mj-text></mj-body></mjml>',
    );

    $text = $compiler->renderText()->toHtml();

    expect($text)
        ->toContain('Plain MJML body')
        ->not->toContain('<table');
});

it('invokes the configured mjml binary path', function (): void {
    $binary = (string) config('mjml.path_to_binary');

    expect($binary)
        ->not->toContain('fake-mjml')
        ->and(is_file($binary))->toBeTrue();
});

it('writes compiled html under the configured view compiled path', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Cache probe</mj-text></mj-body></mjml>',
    );

    $compiler->renderHtml();

    $htmlFiles = array_filter(
        scandir(CompiledStorage::viewsPath()) ?: [],
        fn (string $file): bool => str_ends_with($file, '.html'),
    );

    expect($htmlFiles)->not->toBeEmpty();
});

it('reuses cached html when renderHtml is called twice with the same mjml content', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Cached E2E</mj-text></mj-body></mjml>',
    );

    $compiler->renderHtml();
    $htmlCountAfterFirst = count(array_filter(
        File::files(config('view.compiled')),
        fn ($file): bool => str_ends_with($file->getFilename(), '.html'),
    ));

    $secondHtml = $compiler->renderHtml()->toHtml();

    expect($secondHtml)->toContain('Cached E2E')
        ->and(count(array_filter(
            File::files(config('view.compiled')),
            fn ($file): bool => str_ends_with($file->getFilename(), '.html'),
        )))->toBe($htmlCountAfterFirst);
});

it('compiles nested includes through header and snippet partials', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.with-header-chain', [
            'headline' => 'Weekly update',
            'snippet' => 'Snippet body',
        ]),
        [
            'headline' => 'Weekly update',
            'snippet' => 'Snippet body',
        ],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Weekly update')
        ->toContain('Snippet body')
        ->not->toContain('<mj-include')
        ->not->toContain('{{ $');
});

it('compiles nested views with parent-relative mj-include paths', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.nested.notification', [
            'name' => 'Mikael',
            'footer' => 'Acme Inc',
        ]),
        ['name' => 'Mikael', 'footer' => 'Acme Inc'],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toContain('Greeting Mikael')
        ->toContain('Acme Inc')
        ->toContain('<table')
        ->not->toContain('<mj-include');
});

it('omits conditional include content when blade conditions are false', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.backend.contract-style', [
            'greeting' => 'Hello',
            'showSignature' => false,
            'signature' => 'Must not appear',
        ]),
        [
            'greeting' => 'Hello',
            'showSignature' => false,
            'signature' => 'Must not appear',
        ],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toContain('Hello')
        ->toContain('The Acme team')
        ->not->toContain('Must not appear')
        ->not->toContain('@if');
});

it('compiles mj-button links into real html anchors', function (): void {
    $actionUrl = 'https://laravel-mail-mjml.test/contracts/42';

    $compiler = new MjmlCompiler(
        view('mail.with-button', [
            'actionUrl' => $actionUrl,
            'label' => 'Open contract',
        ]),
        [
            'actionUrl' => $actionUrl,
            'label' => 'Open contract',
        ],
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain($actionUrl)
        ->toContain('Open contract')
        ->toMatch('/<a[^>]+href=["\']'.preg_quote($actionUrl, '/').'["\']/i')
        ->not->toContain('<mj-button');
});

it('builds mailable content through the trait with the real mjml cli', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function content(): Content
        {
            return $this->mjmlContentDefinition('mail.simple', [
                'name' => 'Mailable E2E',
                'footer' => 'Real MJML',
            ]);
        }
    };

    $html = (string) $mailable->content()->html;

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Hello Mailable E2E')
        ->toContain('Real MJML')
        ->toContain('<table')
        ->not->toContain('{{ $');
});

it('builds html and text parts from a mailable via buildMjmlView', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;
    };

    $built = $mailable
        ->mjml('mail.simple', ['name' => 'Build path', 'footer' => 'Text part'])
        ->buildMjmlView();

    $html = $built['html']->toHtml();
    $text = $built['text']->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Hello Build path')
        ->and($text)
        ->toContain('Build path')
        ->toContain('Text part')
        ->not->toContain('<table');
});
