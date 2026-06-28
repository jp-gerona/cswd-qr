# CSWD QR Batch Generator — Agent & Contributor Guide

Batch QR code creator that renders QR codes into a printable PDF for the CITY OF BIÑAN's CSWD Office.

## Hard Constraints (Non-negotiables)

- **PHP only.** No other languages.
- **Composer only** for package management. Never hand-edit `vendor/`.
- **Verbose, complete variable names.** No abbreviations — code must be understandable just by reading it.
- **Don't touch unrelated code.** Every changed line traces to the current task. No drive-by refactors, reformatting, or comment edits.
- **Dead code: mention, don't delete.** Surface it; leave removal to an explicit request.
- **CI4 rules:**
  - Use `php spark` for scaffolding and migrations.
  - Config lives in `app/Config`; routes in `app/Config/Routes.php`.
  - No business logic in Views.
  - Secrets in `.env` — never committed.
  - Follow MVC.

## Stack (pinned)

CI4 4.7.3 · PHP 8.2.30 · Composer 2.9.7 · MariaDB 10.4.28 · Apache 2.4.56 · XAMPP 8.2.4 (Mac Intel) · PHPUnit ^10.5.16
Composer libs: dompdf/dompdf · chillerlan/php-qrcode · twbs/bootstrap 5.3.8 · components/jquery 3.7.1.

Do not bump these without an explicit request.
Runs under XAMPP `htdocs`.

## Core / Behavioral Guidelines


### 1. Think Before Coding
Don't assume. Don't hide confusion. Surface tradeoffs. State assumptions explicitly; if
uncertain, ask. If multiple interpretations exist, present them. If a simpler approach
exists, say so. If something is unclear, stop and name it.

### 2. Simplicity First
Minimum code that solves the problem. Nothing speculative. No features beyond what was
asked, no abstractions for single-use code, no unrequested flexibility, no error handling
for impossible scenarios. If 200 lines could be 50, rewrite.

### 3. Surgical Changes
Touch only what you must. Don't improve adjacent code, don't refactor what isn't broken,
match existing style. Remove only the orphans your own changes created; leave pre-existing
dead code (mention it).

### 4. Goal-Driven Execution
Define success criteria, loop until verified. Turn tasks into verifiable goals ("add
validation" → "write tests for invalid inputs, then make them pass"). State a brief plan
for multi-step work.

## Commands

- `composer install` — install dependencies
- `php spark serve` — run dev server
- `composer test` — run PHPUnit
- `php spark <generator>` — scaffold (controllers, models, migrations)

## Tests

PHPUnit via `composer test`. Tests in `tests/`. Write the test first (reproduce/verify), then make it pass.

## Architecture

MVC under `app/`. Controllers → Models → Views. Flow: input batch → generate a QR code per record → compose codes into a printable PDF. QR and PDF libraries are not yet chosen; install via Composer when selected.
