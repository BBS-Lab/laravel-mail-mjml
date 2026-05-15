<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;
use BBSLab\LaravelMjml\Tests\Support\CompiledStorage;
use BBSLab\LaravelMjml\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

uses(TestCase::class)->in(
    'ArchTest.php',
    'Mail',
    'Mjml',
    'ServiceProviderTest.php',
);

beforeAll(function (): void {
    CompiledStorage::ensureExists(CompiledStorage::viewsPath());
    CompiledStorage::wipe();
});

function wipeCompiledStorage(?string $directory = null): void
{
    CompiledStorage::wipe($directory);
}

/**
 * @return array<string, HtmlString>|string
 */
function invokeMjmlBuildView(object $mailable): array|string
{
    $method = new ReflectionMethod($mailable, 'buildView');
    $method->setAccessible(true);

    return $method->invoke($mailable);
}

function invokeMjmlMakeCompiler(object $mailable): void
{
    $method = new ReflectionMethod($mailable, 'makeMjmlCompiler');
    $method->setAccessible(true);

    $method->invoke($mailable);
}

/**
 * Assert Blade directives were evaluated and are not left in the output.
 *
 * @param  list<string>  $unresolvedPatterns  Literal strings that must be absent (e.g. '{{ $name }}')
 * @param  list<string>  $resolvedValues  Values that must appear after rendering
 */
function expectBladeResolved(string $html, array $unresolvedPatterns = [], array $resolvedValues = []): void
{
    foreach ($unresolvedPatterns as $pattern) {
        expect($html)->not->toContain($pattern);
    }

    foreach ($resolvedValues as $value) {
        expect($html)->toContain($value);
    }
}

/**
 * @param  array<string, mixed>  $data
 */
function resolveMjmlWithBlade(
    MjmlIncludeResolver $resolver,
    string $mjml,
    string $baseDirectory,
    array $data = [],
): string {
    return Blade::render($resolver->resolve($mjml, $baseDirectory), $data);
}

/**
 * Assert that typical Blade syntax from MJML includes was evaluated.
 *
 * @param  list<string>  $resolvedValues
 */
function expectIncludeBladeResolved(string $html, array $resolvedValues = []): void
{
    expectBladeResolved($html, [
        '{{ $',
        '@if',
        '@endif',
        '@foreach',
        '@endforeach',
        '<mj-include',
        "trans('",
        "config('",
        "asset('",
    ], $resolvedValues);
}
