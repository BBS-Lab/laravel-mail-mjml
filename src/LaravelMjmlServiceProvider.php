<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml;

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;
use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMjmlServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-mail-mjml')
            ->hasConfigFile('mjml');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MjmlIncludeResolver::class, fn (): MjmlIncludeResolver => new MjmlIncludeResolver);

        $this->app->bind(MjmlCompiler::class, function ($app, array $parameters): MjmlCompiler {
            $mjml = $parameters['mjml'] ?? '';

            return new MjmlCompiler(
                mjmlViewOrMjml: $mjml,
                data: $parameters['data'] ?? [],
                basePath: $parameters['basePath'] ?? null,
                includeResolver: $app->make(MjmlIncludeResolver::class),
            );
        });
    }
}
