<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Tests;

use BBSLab\LaravelMjml\LaravelMjmlServiceProvider;
use BBSLab\LaravelMjml\Tests\Support\CompiledStorage;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        CompiledStorage::ensureExists(CompiledStorage::viewsPath());
    }

    protected function tearDown(): void
    {
        config()->set('view.compiled', CompiledStorage::viewsPath());

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelMjmlServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.compiled', CompiledStorage::viewsPath());
        $app['config']->set('view.paths', [__DIR__.'/fixtures/views']);
        $app['config']->set('app.name', 'Acme');
        $app['config']->set('app.url', 'https://laravel-mail-mjml.test');
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
        $app->useLangPath(__DIR__.'/fixtures/lang');
        $app['config']->set('mjml.auto_detect_path', false);
        $app['config']->set('mjml.path_to_binary', __DIR__.'/bin/fake-mjml');
        $app['config']->set('mjml.node_path', '');
        $app['config']->set('mjml.process_includes_with_blade', true);
        $app['config']->set('mjml.rerender_blade_after_compile', false);
    }
}
