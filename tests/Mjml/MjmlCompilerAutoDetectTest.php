<?php

declare(strict_types=1);

use BBSLab\LaravelMjml\Mjml\MjmlCompiler;

covers(MjmlCompiler::class);

it('uses the auto detected binary in the command line', function (): void {
    config()->set('mjml.auto_detect_path', true);

    $compiler = new MjmlCompiler('<mjml><mj-body></mj-body></mjml>');

    $htmlPath = new ReflectionProperty(MjmlCompiler::class, 'compiledHtmlPath');
    $htmlPath->setAccessible(true);
    $htmlPath->setValue($compiler, config('view.compiled').'/preview.html');

    expect($compiler->buildCommandLine())->toContain('node_modules/.bin/mjml');
});
