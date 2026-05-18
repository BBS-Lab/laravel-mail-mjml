<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Concerns\BuildsMjmlMail;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\View;

covers(BuildsMjmlMail::class);

it('returns a content definition for mjml mails', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function content(): Content
        {
            return $this->mjmlContentDefinition('mail.simple', [
                'name' => 'Content API',
                'footer' => 'Footer',
            ]);
        }
    };

    $content = $mailable->content();

    expectBladeResolved((string) $content->html, [
        '{{ $name }}',
        '{{ $footer }}',
    ], [
        'Content API',
        'Footer',
    ]);
});

it('builds mjml through buildView when mjml is configured', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;
    };

    $mailable->mjml('mail.simple', ['name' => 'BuildView', 'footer' => 'Ok']);

    $view = invokeMjmlBuildView($mailable);

    expect($view)->toBeArray()
        ->and($view['html']->toHtml())->toContain('BuildView');
});

it('builds mjml views from a mailable', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function envelope(): Envelope
        {
            return new Envelope(subject: 'Test');
        }

        public function content(): Content
        {
            return $this->mjmlContentDefinition('mail.simple', [
                'name' => 'Mikael',
                'footer' => 'Acme support',
            ]);
        }
    };

    $built = $mailable
        ->mjml('mail.simple', ['name' => 'Mikael', 'footer' => 'Acme support'])
        ->buildMjmlView();

    expectBladeResolved($built['html']->toHtml(), [
        '{{ $name }}',
        '{{ $footer }}',
    ], [
        'Mikael',
        'Acme support',
    ]);
});

it('falls back to the default mailable view builder', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;
    };

    $mailable->view('mail.html-only', ['title' => 'Hello']);

    expect(invokeMjmlBuildView($mailable))->toBe('mail.html-only');
});

it('throws when the mjml view cannot be rendered as blade', function (): void {
    View::shouldReceive('make')
        ->once()
        ->andReturn(Mockery::mock(ViewContract::class));

    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;
    };

    $mailable->mjml('mail.simple');

    invokeMjmlMakeCompiler($mailable);
})->throws(RuntimeException::class, 'renderable Blade views');

it('builds mjml from raw mjml content using mjmlContent without a view name', function (): void {
    $mailable = new class extends Mailable
    {
        use BuildsMjmlMail;

        public function __construct(public string $name = 'Via content') {}
    };

    $built = $mailable
        ->mjmlContent('<mjml><mj-body><mj-text>Hi {{ $name }}</mj-text></mj-body></mjml>')
        ->buildMjmlView();

    expectBladeResolved($built['html']->toHtml(), [
        '{{ $name }}',
    ], [
        'Hi Via content',
    ]);
});
