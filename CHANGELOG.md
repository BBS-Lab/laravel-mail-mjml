# Changelog

All notable changes to `laravel-mail-mjml` will be documented in this file.

## 1.0.0 - 2026-05-15

### Added

- PHP `^8.3` minimum (Pest 4 dev toolchain; aligns with Laravel 13 floor)
- Initial release as a maintained fork of `asahasrabuddhe/laravel-mjml`
- `BuildsMjmlMail` trait for `Illuminate\Mail\Mailable` (no package base class)
- Blade rendering for `<mj-include>` partials before a single MJML CLI pass (Option B pipeline)
- `mjmlContentDefinition()` helper for Laravel `Content` objects
- HTML + plain-text parts with compile cache under `view.compiled`
- Pest test suite: 65 package tests (fake MJML CLI) + 23 E2E tests (real `mjml` binary)
- PHPStan level 8, 100% line coverage on `src/`, mutation testing (≥ 65% on covered code)
- CI: PHP×Laravel matrix (11–13, Laravel-supported PHP versions, `prefer-lowest` / `prefer-stable`)
- CI: MJML E2E workflow (same PHP×Laravel matrix, Node 24, `npm ci`)
- `composer clean` auto-run before `test-mjml`, `test-coverage`, and `test-mutation`
