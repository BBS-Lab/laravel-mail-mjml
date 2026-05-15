<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Tests\MjmlE2E;

use BBSLab\LaravelMjml\Tests\Support\MjmlBinary;
use BBSLab\LaravelMjml\Tests\TestCase;

abstract class MjmlE2eTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (! MjmlBinary::isAvailable()) {
            $this->markTestSkipped('MJML CLI not found. Run: npm install');
        }

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $binary = MjmlBinary::resolve();

        if ($binary === null) {
            return;
        }

        $app['config']->set('mjml.auto_detect_path', false);
        $app['config']->set('mjml.path_to_binary', $binary);
        $app['config']->set('mjml.node_path', (string) env('MJML_NODE_PATH', 'node'));
    }
}
