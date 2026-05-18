<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Tests\Support\CompiledStorage;
use Illuminate\Support\Facades\File;

covers(MjmlCompiler::class);

beforeEach(function (): void {
    wipeCompiledStorage();

    config()->set('mjml.auto_detect_path', false);
    config()->set('mjml.path_to_binary', __DIR__.'/../bin/fake-mjml');
    config()->set('mjml.process_includes_with_blade', true);
    config()->set('mjml.rerender_blade_after_compile', false);
});

it('builds a command line without a node prefix when node_path is empty', function (): void {
    config()->set('mjml.node_path', '');

    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');
    $compiler->renderHtml();

    $command = $compiler->buildCommandLine();

    expect($command)->not->toStartWith('node ')
        ->and($command)->toContain('fake-mjml');
});

it('builds a command line with a configured binary when auto detect is disabled', function (): void {
    $binary = __DIR__.'/../bin/fake-mjml';
    config()->set('mjml.path_to_binary', $binary);

    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');
    $compiler->renderHtml();

    expect($compiler->buildCommandLine())->toContain($binary);
});

it('includes the mjml config file path for blade based templates', function (): void {
    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Config path', 'footer' => 'Ok']),
        ['name' => 'Config path', 'footer' => 'Ok'],
    );

    $compiler->renderHtml();

    expect($compiler->buildCommandLine())->toContain('--config.filePath=');
});

it('stores compiled artifacts under view.compiled without a trailing slash', function (): void {
    $compiledDirectory = CompiledStorage::ensureExists(
        CompiledStorage::root().'/no-trailing-slash',
    );

    config()->set('view.compiled', $compiledDirectory);
    wipeCompiledStorage($compiledDirectory);

    $compiler = new MjmlCompiler('<mjml><mj-body><mj-text>Slash</mj-text></mj-body></mjml>');
    $compiler->renderHtml();

    $files = File::files($compiledDirectory);

    expect($files)->not->toBeEmpty();
    expect($files[0]->getPathname())->toStartWith($compiledDirectory.DIRECTORY_SEPARATOR);
});

it('omits the mjml config file path for raw mjml strings', function (): void {
    $compiler = new MjmlCompiler('<mjml><mj-body><mj-text>Raw</mj-text></mj-body></mjml>');
    $compiler->renderHtml();

    expect($compiler->buildCommandLine())->not->toContain('--config.filePath=');
});

it('decodes html entities in the plain text part', function (): void {
    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Tom &amp; Jerry</mj-text></mj-body></mjml>',
        processRunner: function (string $command): void {
            preg_match("/-o '([^']+)'$/", $command, $matches);
            file_put_contents(
                $matches[1],
                '<html><body><p>Tom &amp; Jerry</p><p>Line two</p><p>Line three</p></body></html>',
            );
        },
    );

    expect($compiler->renderText()->toHtml())->toContain('Tom & Jerry');
});

it('skips include preprocessing when explicitly disabled in config', function (): void {
    config()->set('mjml.process_includes_with_blade', false);

    $compiler = new MjmlCompiler(
        view('mail.nested.notification', ['name' => 'Ada', 'footer' => 'Acme']),
        ['name' => 'Ada', 'footer' => 'Acme'],
    );

    $compiler->renderHtml();

    $mjmlPath = null;

    foreach (File::files(config('view.compiled')) as $file) {
        if (str_ends_with($file->getFilename(), '.mjml.php')) {
            $mjmlPath = $file->getPathname();

            break;
        }
    }

    expect($mjmlPath)->not->toBeNull();
    expect(file_get_contents($mjmlPath))->toContain('<mj-include');
});
