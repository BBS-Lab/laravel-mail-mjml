<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Tests\Support;

use BBSLab\LaravelMjml\Mjml\MjmlIncludeResolver;

class SpyMjmlIncludeResolver extends MjmlIncludeResolver
{
    public bool $resolved = false;

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(string $mjml, string $baseDirectory, array $data = []): string
    {
        $this->resolved = true;

        return '<mjml><mj-body><mj-text>Resolved by spy</mj-text></mj-body></mjml>';
    }
}
