# CSWD QR Batch Generator

MVP. Batch QR code creator that renders QR codes into a printable PDF for the City Social Welfare and Development Office (CSWD).

## Stack (pinned)

CI4 4.7.3 · PHP 8.2.30 · Composer 2.9.7 · MariaDB 10.4.28 · Apache 2.4.56 · XAMPP 8.2.4 (Mac Intel) · PHPUnit ^10.5.16

Runs under XAMPP `htdocs`.

## Hard Constraints (non-negotiable)

- **PHP only.** No other languages.
- **Composer only** for package management. Never hand-edit `vendor/`.
- **Verbose, complete variable names.** No abbreviations — code must be understandable just by reading it.
- **Don't touch unrelated code.**
- **Dead code: mention it, don't delete it.**
- **CI4 rules:**
  - Use `php spark` for scaffolding and migrations.
  - Config lives in `app/Config`; routes in `app/Config/Routes.php`.
  - No business logic in Views.
  - Secrets in `.env` — never committed.
  - Follow MVC.

## Core Behavior

1. **Think before coding.** State assumptions; if uncertain, ask. Surface tradeoffs and simpler approaches instead of picking silently.
2. **Simplicity first.** Minimum code that solves the problem. No speculative features, abstractions, or config that wasn't asked for.
3. **Surgical changes.** Every changed line traces to the request. Match existing style. Only remove orphans your own change created.
4. **Goal-driven execution.** Turn tasks into verifiable goals (write the failing test/check first, then make it pass), then loop until verified.

## Commands

- `composer install` — install dependencies
- `php spark serve` — run dev server
- `composer test` — run PHPUnit
- `php spark <generator>` — scaffold (controllers, models, migrations)

## Tests

PHPUnit via `composer test`. Tests in `tests/`. Write the test first (reproduce/verify), then make it pass.

## Architecture

MVC under `app/`. Controllers → Models → Views. Flow: input batch → generate a QR code per record → compose codes into a printable PDF. QR and PDF libraries are not yet chosen; install via Composer when selected.
