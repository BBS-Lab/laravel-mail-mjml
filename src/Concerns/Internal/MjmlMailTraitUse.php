<?php

declare(strict_types=1);

namespace BBSLab\LaravelMjml\Concerns\Internal;

use BBSLab\LaravelMjml\Concerns\BuildsMjmlMail;
use Illuminate\Mail\Mailable;

/**
 * @internal Satisfies static analysis for {@see BuildsMjmlMail}; not part of the public API.
 */
class MjmlMailTraitUse extends Mailable
{
    use BuildsMjmlMail;
}
