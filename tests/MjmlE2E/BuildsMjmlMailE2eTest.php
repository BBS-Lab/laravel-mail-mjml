<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Concerns\BuildsMjmlMail;
use BBSLab\LaravelMjml\Tests\MjmlE2E\MjmlE2eTestCase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

uses(MjmlE2eTestCase::class);

covers(BuildsMjmlMail::class);

it('builds mjml from mjmlContent without a named view', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function __construct(public string $name = 'Raw MJML') {}
    };

    $built = $mailable
        ->mjmlContent('<mjml><mj-body><mj-text>Hi {{ $name }}</mj-text></mj-body></mjml>')
        ->buildMjmlView();

    $html = $built['html']->toHtml();

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Hi Raw MJML')
        ->not->toContain('data-mjml-compiled');
});

it('returns html and text through buildView when mjml is configured', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;
    };

    $mailable->mjml('mail.simple', ['name' => 'buildView', 'footer' => 'E2E']);

    $view = invokeMjmlBuildView($mailable);

    expect($view)->toBeArray()
        ->and($view['html']->toHtml())->toMatch('/<!doctype html>/i')
        ->and($view['html']->toHtml())->toContain('Hello buildView')
        ->and($view['text']->toHtml())->toContain('buildView')
        ->and($view['text']->toHtml())->not->toContain('<table');
});

it('compiles full-layout mails with head header button and rich footer', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function content(): Content
        {
            return $this->mjmlContentDefinition('mail.full-layout', [
                'subject' => 'Contract update',
                'headline' => 'Acme Notifications',
                'snippet' => 'Your document is ready',
                'reference' => 'DOC-2026-042',
                'introHtml' => '<em>Please review</em> the attached terms.',
                'actionUrl' => 'https://laravel-mail-mjml.test/sign/42',
                'actionLabel' => 'Sign now',
                'showSignature' => true,
                'signature' => 'Acme support',
            ]);
        }
    };

    $html = (string) $mailable->content()->html;
    $text = (string) $mailable->content()->text;

    expect($html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Acme Notifications')
        ->toContain('Your document is ready')
        ->toContain('DOC-2026-042')
        ->toContain('The Acme team')
        ->toContain('Please review')
        ->toContain('<em>')
        ->toContain('https://laravel-mail-mjml.test/sign/42')
        ->toContain('Sign now')
        ->toContain('Acme support')
        ->toContain('/images/email-footer.png')
        ->not->toContain('<mj-')
        ->not->toContain('{{ ')
        ->and($text)
        ->toContain('Sign now')
        ->toContain('DOC-2026-042')
        ->not->toContain('<table');
});

it('delivers production-like contract html and text in one mailable content definition', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function envelope(): Envelope
        {
            return new Envelope(subject: 'Contract');
        }

        public function content(): Content
        {
            return $this->mjmlContentDefinition('mail.backend.contract-style', [
                'greeting' => 'Bonjour',
                'showSignature' => true,
                'signature' => 'Acme support',
            ]);
        }
    };

    $content = $mailable->content();

    expect((string) $content->html)
        ->toMatch('/<!doctype html>/i')
        ->toContain('Bonjour')
        ->toContain('Acme support')
        ->and((string) $content->text)
        ->toContain('Bonjour')
        ->toContain('The Acme team');
});

it('inlines multiple mj-include partials in a single mail', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;
    };

    $built = $mailable
        ->mjml('mail.full-layout', [
            'headline' => 'Multi',
            'snippet' => 'Include',
            'reference' => 'REF-1',
            'introHtml' => 'Intro',
            'actionUrl' => 'https://example.test/go',
            'actionLabel' => 'Go',
            'showSignature' => false,
            'signature' => '',
        ])
        ->buildMjmlView();

    expect($built['html']->toHtml())
        ->toContain('Multi')
        ->toContain('Include')
        ->toContain('The Acme team')
        ->not->toContain('<mj-include');
});
