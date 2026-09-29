# AGENTS.md

## Project overview

`konradmichalik/php-progress` is a dependency-free CLI progress bar and spinner library for PHP. It renders a single dynamic line with sub-cell smooth bars and truecolor gradients that reflows as information appears.

- PHP `>=8.1`, only `ext-mbstring` at runtime
- Framework-agnostic. Symfony Console or TYPO3 commands pass `$output->getStream()` to `->to()`
- License: GPL-3.0-or-later

## Structure

- `src/`: library code, PSR-4 namespace `KonradMichalik\PhpProgress\`
  - `Progress.php` is the facade with the entry points, `Live.php` drives a live bar or spinner
  - `Render/`: `Layout` and `Renderer` compose the line from segments (`Segment`, `FlexSegment`, `Chunk`)
  - `Segment/`: one class per line element (bar, spinner, percent, count, elapsed, ETA, rate, label, fields)
  - `Style/`: `Theme` and `Spinners`
  - `Terminal/`: `Ansi` and `Capabilities` (color and width detection)
  - `Process/`: `Proc` for wrapping external processes
  - `Support/`: `Text` helpers
- `tests/`: PHPUnit tests, mirrors the `src/` layout (namespace `KonradMichalik\PhpProgress\Tests\`)
- `examples/`: runnable demos (`demo.php`, `showcase.php`)
- `docs/`: screencast for the README

## Development commands

```bash
composer install
composer test                # phpunit
composer test:coverage       # line coverage, needs Xdebug or PCOV
composer analyse             # phpstan analyse
composer check               # analyse + test, what CI runs
composer validate --strict   # also run in CI
php examples/showcase.php all   # see it in a real terminal
```

## Testing

- PHPUnit `^10.5`, config in `phpunit.xml`. `failOnWarning` and `failOnRisky` are on, and tests must not produce output (`beStrictAboutOutputDuringTests`)
- Tests stay deterministic through an injected fake clock (`tests/Clock.php`) and simulated terminal capabilities. Never depend on a real TTY, the wall clock or the environment
- Base class is `tests/TestCase.php`, signal handling is covered by `tests/SignalTest.php` with `tests/signal_worker.php`
- Run a single test:

```bash
vendor/bin/phpunit --filter testFooBar
vendor/bin/phpunit tests/Render/LayoutTest.php
```

## Code style and static analysis

- Every PHP file starts with `declare(strict_types=1);`
- PHPStan runs at level `max` on `src/` (`phpstan.neon`)
- CI lints `src` and `tests` with `php -l` on PHP 8.1 to 8.4, so keep code compatible with 8.1
- No PHP-CS-Fixer, Rector or other linter is configured

## Git workflow

- Commit format: `<type>: <description>` with type one of feat, fix, refactor, docs, test, chore, perf, ci
- No co-author trailers
- CI (`.github/workflows/ci.yml`) runs tests on PHP 8.1 to 8.4, PHPStan on 8.3 and coverage on 8.3
- Open a pull request, ideally referencing an issue. See `CONTRIBUTING.md`
