<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use BBSLab\LaravelMjml\Tests\Support\CompiledStorage;
use BBSLab\LaravelMjml\Tests\Support\SpyMjmlIncludeResolver;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

covers(MjmlCompiler::class);

beforeEach(function (): void {
    wipeCompiledStorage();
});

it('uses a custom base path when resolving includes from raw mjml', function (): void {
    $basePath = __DIR__.'/../fixtures/views/mail';

    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-include path="mjml/footer.mjml" /></mj-body></mjml>',
        ['footer' => 'Custom base'],
        basePath: $basePath,
    );

    expect($compiler->renderHtml()->toHtml())->toContain('Custom base');
});

it('uses the include resolver registered in the container', function (): void {
    $spy = new SpyMjmlIncludeResolver;
    $this->app->instance(MjmlIncludeResolver::class, $spy);

    $compiler = app()->make(MjmlCompiler::class, [
        'mjml' => '<mjml><mj-body><mj-include path="mjml/footer.mjml" /></mj-body></mjml>',
        'data' => [],
    ]);

    $html = $compiler->renderHtml()->toHtml();

    expect($spy->resolved)->toBeTrue()
        ->and($html)->toContain('Resolved by spy');
});

it('builds the command line with an explicit node binary prefix', function (): void {
    config()->set('mjml.auto_detect_path', false);
    config()->set('mjml.node_path', 'node');
    config()->set('mjml.path_to_binary', __DIR__.'/../bin/fake-mjml');

    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');

    $htmlPath = new ReflectionProperty(MjmlCompiler::class, 'compiledHtmlPath');
    $htmlPath->setAccessible(true);
    $htmlPath->setValue($compiler, config('view.compiled').'/edge-case.html');

    expect($compiler->buildCommandLine())->toStartWith('node ');
});

it('processes includes when the config key is missing and defaults apply', function (): void {
    $mjmlConfig = config('mjml');
    unset($mjmlConfig['process_includes_with_blade']);
    config()->set('mjml', $mjmlConfig);

    $compiler = new MjmlCompiler(
        view('mail.simple', ['name' => 'Default', 'footer' => 'Default footer']),
        ['name' => 'Default', 'footer' => 'Default footer'],
    );

    expectBladeResolved($compiler->renderHtml()->toHtml(), [
        '{{ $footer }}',
    ], [
        'Default footer',
    ]);
});

it('throws when an mjml view has no filesystem path', function (): void {
    $view = Mockery::mock(View::class);
    $view->shouldReceive('getPath')->andReturn('');
    $view->shouldReceive('getData')->andReturn([]);

    (new MjmlCompiler($view))->renderHtml();
})->throws(RuntimeException::class, 'resolvable filesystem path');

it('renders blade on raw mjml strings when include preprocessing is disabled', function (): void {
    config()->set('mjml.process_includes_with_blade', false);

    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Hello {{ $name }}</mj-text></mj-body></mjml>',
        ['name' => 'String path'],
    );

    expect($compiler->renderHtml()->toHtml())->toContain('Hello String path');
});

it('does not rerender blade after compile when disabled in config', function (): void {
    config()->set('mjml.rerender_blade_after_compile', false);

    $compiler = new MjmlCompiler(
        '<mjml><mj-body><mj-text>Static output</mj-text></mj-body></mjml>',
    );

    expect($compiler->renderHtml()->toHtml())->toContain('Static output');
});

it('stores compiled files under the configured view path', function (): void {
    $customCompiled = CompiledStorage::ensureExists(CompiledStorage::root().'/custom-compiled');
    config()->set('view.compiled', $customCompiled);
    wipeCompiledStorage($customCompiled);

    $compiler = new MjmlCompiler('<mjml><mj-body><mj-text>Path</mj-text></mj-body></mjml>');
    $compiler->renderHtml();

    expect(File::files($customCompiled))->not->toBeEmpty();
});
