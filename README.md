# Laravel Mail MJML

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bbs-lab/laravel-mail-mjml.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/laravel-mail-mjml)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/BBS-Lab/laravel-mail-mjml/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/BBS-Lab/laravel-mail-mjml/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/bbs-lab/laravel-mail-mjml.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/laravel-mail-mjml)

Build responsive transactional e-mails with [MJML](https://mjml.io/) and Laravel `Mailable` classes — with **Blade inside your templates and `<mj-include>` partials**.

Maintained fork of [`asahasrabuddhe/laravel-mjml`](https://github.com/asahasrabuddhe/laravel-mjml). No application-level `RerenderMjml` workaround required.

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Recommended layout](#recommended-layout)
- [Usage](#usage)
- [How it works](#how-it-works)
- [Configuration](#configuration)
- [Compiler API](#compiler-api)
- [Migrating from the original package](#migrating-from-the-original-package)
- [Quality & testing](#quality--testing)
- [Changelog](#changelog)

## Features

- **`BuildsMjmlMail` trait** — add MJML to any `Illuminate\Mail\Mailable`
- **Blade in root views and includes** — `{{ }}`, `@if`, `trans()`, `asset()`, `config()`, nested `<mj-include>`
- **HTML + plain text** — text part generated from compiled HTML
- **Compile cache** — artifacts stored under Laravel’s `view.compiled` path
- **Strict quality bar** — Pest, PHPStan level 8, 100% line coverage on `src/`

## Requirements

| Tool | Version |
| --- | --- |
| PHP | `^8.2` (see CI matrix per Laravel version) |
| Laravel | `^11`, `^12`, or `^13` |
| Node.js | 20+ (CI E2E uses Node 24) |
| MJML CLI | via `npm install mjml` (or custom binary in config) |

## Installation

```bash
composer require bbs-lab/laravel-mail-mjml
npm install --save-dev mjml
```

Publish configuration (optional):

```bash
php artisan vendor:publish --tag="laravel-mail-mjml-config"
```

The package auto-registers `LaravelMjmlServiceProvider`. No manual setup beyond the trait on your mailables.

## Quick start

**1. Trait on your mailable**

```php
<?php

namespace App\Mail;

use BBSLab\LaravelMjml\Concerns\BuildsMjmlMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use BuildsMjmlMail;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome');
    }

    public function content(): Content
    {
        return $this->mjmlContentDefinition('mail.welcome', [
            'name' => $this->name,
        ]);
    }
}
```

**2. MJML Blade view** — `resources/views/mail/welcome.blade.php`

```blade
<mjml>
    <mj-body>
        <mj-section>
            <mj-column>
                <mj-text>Hello {{ $name }}</mj-text>
            </mj-column>
        </mj-section>
        <mj-include path="../mjml/footer.mjml" />
    </mj-body>
</mjml>
```

**3. Send as usual**

```php
Mail::to($user)->send(new WelcomeMail($user->name));
```

## Recommended layout

Structure that works well in production (one Blade shell per mail, reusable MJML partials):

```text
resources/views/mail/
├── welcome.blade.php          # <mjml> shell + <mj-include>
├── backend/
│   └── reset-password.blade.php
└── mjml/
    ├── head.mjml
    ├── header.mjml
    └── footer.mjml            # Blade: trans(), asset(), @if, etc.
```

**Include paths** are resolved relative to the **view file** that contains `<mj-include>`. From `resources/views/mail/backend/reset-password.blade.php`:

```blade
<mj-include path="../mjml/footer.mjml" />
```

Nested includes inside a partial (e.g. `header.mjml` including `snippet.mjml`) resolve relative to **that partial’s directory**.

## Usage

### Laravel 11+ `Content` API (recommended)

`mjmlContentDefinition()` compiles the view and returns a `Content` instance with `html` and `text`:

```php
public function content(): Content
{
    return $this->mjmlContentDefinition('mail.welcome', [
        'name' => $this->name,
        'actionUrl' => $this->actionUrl,
    ]);
}
```

### Classic `build()` / `buildView()` flow

```php
public function build(): self
{
    return $this
        ->mjml('mail.welcome', ['name' => $this->name])
        ->subject('Welcome');
}
```

The trait overrides `buildView()` when `mjml()` or `mjmlContent()` was called. Otherwise the default Laravel view behaviour applies.

### Raw MJML string

Useful for tests or dynamically built markup:

```php
return $this
    ->mjmlContent('<mjml><mj-body><mj-text>Hi {{ $name }}</mj-text></mj-body></mjml>')
    ->subject('Hi');
```

Pass data through the mailable’s `viewData` / constructor as with any Blade view.

### Partial with Blade helpers

`resources/views/mail/mjml/footer.mjml`:

```xml
<mj-section>
    <mj-column>
        <mj-image
            src="{{ asset('images/email-footer.png') }}"
            alt="{{ config('app.name') }}" />
        <mj-text>{{ trans('mail.footer.team') }}</mj-text>
        <mj-text>
            @if ($showSignature)
                {{ $signature }}
            @endif
        </mj-text>
    </mj-column>
</mj-section>
```

With `mjml.process_includes_with_blade` enabled (default), these directives run **before** the MJML binary is invoked.

### Preview in the browser (optional)

For local preview without sending mail, compile manually:

```php
use BBSLab\LaravelMjml\Mjml\MjmlCompiler;

$html = (new MjmlCompiler(
    view('mail.welcome', ['name' => 'Preview']),
    ['name' => 'Preview'],
))->renderHtml();

return response($html);
```

## How it works

Default pipeline (**one Blade pass before MJML**):

```text
Blade view on disk (mail/*.blade.php)
        │
        ▼
MjmlIncludeResolver — inline <mj-include> partials (raw, recursive)
        │
        ▼
Single Blade::render() on the assembled MJML
        │
        ▼
MJML CLI → HTML (cached by content hash)
        │
        ▼
Html2Text → plain-text part
```

This replaces the legacy pattern from the original package: Blade on the root view, MJML native includes (no Blade in partials), then a second Blade pass on the HTML (`RerenderMjml`). Here, partials are resolved in PHP and Blade runs **once** on the full document before the binary sees it.

**Do not enable** `process_includes_with_blade` and `rerender_blade_after_compile` together — you would run Blade multiple times without benefit.

Legacy escape hatch: set `process_includes_with_blade` to `false` and `rerender_blade_after_compile` to `true` to approximate the old HTML rerender workflow (not recommended for new projects).

## Configuration

Environment variables map to `config/mjml.php`:

```dotenv
MJML_AUTO_DETECT_PATH=true
MJML_PATH_TO_BINARY=
MJML_NODE_PATH=node
MJML_PROCESS_INCLUDES_WITH_BLADE=true
MJML_RERENDER_BLADE_AFTER_COMPILE=false
```

| Key | Description | Default |
| --- | --- | --- |
| `auto_detect_path` | Resolve `base_path('node_modules/.bin/mjml')` | `true` |
| `path_to_binary` | Absolute path when auto-detect is off | `''` |
| `node_path` | Node executable prepended to the CLI command | `node` |
| `process_includes_with_blade` | Inline includes, then one Blade pass on assembled MJML (recommended) | `true` |
| `rerender_blade_after_compile` | Legacy: Blade pass on HTML after MJML (keep `false` with the option above) | `false` |

**Production tip:** set `auto_detect_path` to `false` and `path_to_binary` to a known binary in CI/Docker images where `node_modules` may not exist at runtime.

## Compiler API

For advanced use (custom `basePath`, mocked process, container binding):

```php
use BBSLab\LaravelMjml\Mjml\MjmlCompiler;

$compiler = app(MjmlCompiler::class, [
    'mjml' => view('mail.welcome', $data),
    'data' => $data,
    'basePath' => resource_path('views/mail'),
]);

$html = $compiler->renderHtml();
$text = $compiler->renderText();
```

Registered services: `MjmlIncludeResolver` (singleton), `MjmlCompiler` (bind).

## Migrating from the original package

| Before (`asahasrabuddhe/laravel-mjml`) | After (`bbs-lab/laravel-mail-mjml`) |
| --- | --- |
| `extends Asahasrabuddhe\LaravelMJML\Mail\Mailable` | `extends Illuminate\Mail\Mailable` + `use BuildsMjmlMail` |
| `RerenderMjml` trait in the app | Remove — includes are Blade-rendered by default |
| Same `mjml()` / `mjmlContent()` ergonomics | Same method names on the trait |

```php
// Before
use Asahasrabuddhe\LaravelMJML\Mail\Mailable;

// After
use BBSLab\LaravelMjml\Concerns\BuildsMjmlMail;
use Illuminate\Mail\Mailable;

class WelcomeMail extends Mailable
{
    use BuildsMjmlMail;
}
```

## Quality & testing

Current targets on `main` (local, PHP 8.2+):

| Check | Command | Target |
| --- | --- | --- |
| Unit / integration tests | `composer test` | 65 tests (fake MJML CLI, no Node) |
| MJML E2E tests | `composer test-mjml` | 23 tests with the real `mjml` binary (`npm install` first) |
| **Total** | | **88 tests** |
| Line coverage (`src/`) | `composer test-coverage` | **100%** (enforced `--min=100`) |
| Mutation score | `composer test-mutation` | **≥ 65%** on covered code (`src/`); requires Xdebug |
| Static analysis | `composer analyse` | PHPStan **level 8** |
| Code style | `composer format` | Laravel Pint |
| Clean compile artifacts | `composer clean` | Wipes `tests/storage/`, `storage/`, root `*.html` / `*.mjml.php` (also run automatically before `test-mjml`, `test-coverage`, and `test-mutation`) |

Mutation testing uses [Pest Mutate](https://pestphp.com/docs/mutate). Each test file declares `covers()` for the classes it exercises. The `test-mutation` script runs `composer clean` first, then ensures Pest’s mutate temp directory exists (avoids false-low scores when `.temp/mutations` is missing).

**Contributor workflow:**

```bash
composer install
composer test              # fast suite (no Node)
npm install                # only for MJML E2E
composer test-mjml           # real mjml CLI → responsive HTML
composer test-coverage
composer test-mutation
composer analyse
composer format
composer clean   # manual wipe; test-mjml / test-coverage / test-mutation already call it
```

CI runs the Pest **Package** suite on Ubuntu for each supported Laravel version (11–13) against the PHP versions that Laravel supports (see `exclude` in [run-tests.yml](.github/workflows/run-tests.yml): e.g. no PHP 8.2 on Laravel 12+, no PHP 8.5 on Laravel 11), with `prefer-lowest` and `prefer-stable`. **run-mjml-e2e-tests** uses the same PHP×Laravel matrix (9 jobs, `prefer-stable` only) with Node 24 and `npm ci`. Mutation tests run on push/PR when `src/` or `tests/` change.

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

See [SECURITY](SECURITY.md).

## Credits

- [BBS](https://github.com/bbs-lab)
- [Ajitem Sahasrabuddhe](https://github.com/asahasrabuddhe) for the original `laravel-mjml` package

## License

The MIT License (MIT). See [LICENSE](LICENSE.md).
