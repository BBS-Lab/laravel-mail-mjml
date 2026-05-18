# Contributing

Thank you for considering a contribution to `bbs-lab/laravel-mail-mjml`.

## Development setup

```bash
git clone https://github.com/bbs-lab/laravel-mail-mjml.git
cd laravel-mail-mjml
composer install
npm install   # required only for MJML E2E tests
```

## Quality gates

All of the following should pass before opening a pull request:

| Gate | Command | Expectation |
| --- | --- | --- |
| Tests | `composer test` | Package suite green (fake MJML CLI) |
| MJML E2E | `composer test-mjml` | Real `mjml` binary; requires `npm install` |
| Coverage | `composer test-coverage` | 100% lines on `src/` |
| Mutation | `composer test-mutation` | ≥ 65% mutation score on `src/` (Xdebug required) |
| Static analysis | `composer analyse` | PHPStan level 8, no errors |
| Code style | `composer format` | Laravel Pint (PSR-12) |

`composer test-mjml`, `test-coverage`, and `test-mutation` run `composer clean` first so compile artifacts never leak into `git status`. Use `composer clean` manually after `composer test` if needed.

Generated files belong under `tests/storage/` (gitignored), not the package root.

## Code conventions

- **No `final` classes** in `src/`
- **No `private` members** in `src/` — use `protected` so behaviour stays extensible and testable
- Prefer the **`BuildsMjmlMail` trait** for mailable integration; do not reintroduce a package `Mailable` base class without discussion
- Add or update Pest tests for every behaviour change; declare `covers()` on the classes under test
- Document user-facing changes in `README.md`

## Pull requests

- One feature or fix per pull request
- Clear commit messages (what and why)
- Link related issues when applicable

## Reporting bugs

Open an issue with:

- PHP and Laravel versions
- Package version (`composer show bbs-lab/laravel-mail-mjml`)
- Steps to reproduce
- Expected vs actual behaviour
- Minimal MJML / Blade excerpt when relevant

## Security

Do not open public issues for security vulnerabilities. See [SECURITY](SECURITY.md).
