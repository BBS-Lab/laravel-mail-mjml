<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use Illuminate\Support\Facades\File;

covers(MjmlCompiler::class);
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    wipeCompiledStorage();
});

it('compiles mjml from a blade view with includes', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Ada', 'footer' => 'Acme']),
        ['name' => 'Ada', 'footer' => 'Acme'],
    );

    $html = $compiler->renderHtml();

    expectBladeResolved($html->toHtml(), [
        '{{ $name }}',
        '{{ $footer }}',
        '<mj-include',
    ], [
        'Hello Ada',
        'Acme',
    ]);
});

it('compiles raw mjml strings', function (): void {
    $compiler = new MjmlCompiler('<mjml><mj-body><mj-text>Plain</mj-text></mj-body></mjml>');

    expect($compiler->renderHtml()->toHtml())->toContain('Plain');
});

it('renders a plain text version from html', function (): void {
    $compiler = new MjmlCompiler('<mjml><mj-body><mj-text>Plain</mj-text></mj-body></mjml>');

    expect($compiler->renderText()->toHtml())->toContain('Plain');
});

it('runs the mjml process only once when rendering html and text', function (): void {
    $calls = 0;

    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Once</mj-text></mj-body></mjml>',
        processRunner: function (string $command) use (&$calls): void {
            $calls++;
            preg_match("/-o '([^']+)'$/", $command, $matches);
            file_put_contents($matches[1], '<html><body>Once</body></html>');
        },
    );

    $compiler->renderText();

    expect($calls)->toBe(1);
});

it('reuses cached html output for identical mjml content', function (): void {
    $compiler = new MjmlCompiler('<mjml><mj-body><mj-text>Cached</mj-text></mj-body></mjml>');

    $compiler->renderHtml();
    $compiledFiles = File::files(config('view.compiled'));
    $htmlFiles = array_values(array_filter(
        $compiledFiles,
        fn ($file): bool => str_ends_with($file->getFilename(), '.html'),
    ));

    expect($htmlFiles)->toHaveCount(1);

    $compiler->renderHtml();

    expect(File::files(config('view.compiled')))->toHaveCount(count($compiledFiles));
});

it('can disable include preprocessing', function (): void {
    config()->set('mjml.process_includes_with_blade', false);

    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Ada', 'footer' => 'Acme']),
    );

    $html = $compiler->renderHtml()->toHtml();

    expect($html)->toContain('<mj-include');
});

it('can rerender blade after mjml compilation', function (): void {
    config()->set('mjml.rerender_blade_after_compile', true);

    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>{{ $name }}</mj-text></mj-body></mjml>',
        ['name' => 'Post compile'],
    );

    expect($compiler->renderHtml()->toHtml())->toContain('Post compile');
});

it('builds a command line using the configured binary', function (): void {
    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');
    $compiler->renderHtml();

    expect($compiler->buildCommandLine())
        ->toContain('fake-mjml');
});

it('runs a custom process runner when provided', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Runner</mj-text></mj-body></mjml>',
        processRunner: function (string $command): void {
            preg_match("/-o '([^']+)'$/", $command, $matches);
            file_put_contents($matches[1], '<html><body>Runner</body></html>');
        },
    );

    expect($compiler->renderHtml()->toHtml())->toContain('Runner');
});

it('throws when the mjml binary fails', function (): void {
    config()->set('mjml.path_to_binary', '/definitely/missing/mjml');

    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');

    $compiler->renderHtml();
})->throws(ProcessFailedException::class);

it('throws when the mjml process fails', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body></mj-body></mjml>',
        processRunner: function (): never {
            $process = Process::fromShellCommandline('false');
            $process->run();

            throw new ProcessFailedException($process);
        },
    );

    $compiler->renderHtml();
})->throws(ProcessFailedException::class);

it('detects the default mjml binary path', function (): void {
    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');

    expect($compiler->detectBinaryPath())->toEndWith('node_modules/.bin/mjml');
});
