# Contributing

Thank you for considering contributing to this project! Every contribution is welcome and helps improve the quality of the project. To ensure a smooth process and maintain high code quality, please follow the steps below.

## Requirements

- PHP >= 8.1
- Composer >= 2.0

## Preparation

```bash
# Clone repository
git clone https://github.com/konradmichalik/php-progress.git
cd php-progress

# Install dependencies
composer install
```

## Run tests

The suite is written with PHPUnit and stays deterministic by injecting a fake
clock and simulated terminal capabilities (no real TTY, wall clock or environment).

```bash
# Run the test suite
composer test

# Run a single test or file
vendor/bin/phpunit --filter testFooBar
vendor/bin/phpunit tests/Render/LayoutTest.php

# Run with line coverage (requires Xdebug or PCOV)
composer test:coverage
```

## Run static code analysis

```bash
# PHPStan, level max
composer analyse
```

## Run all checks

```bash
# Static analysis + tests (what CI runs)
composer check
```

## See it in a real terminal

```bash
# Colour, animation and reflow across the full feature set
php examples/showcase.php all
```

## Submit a pull request

After completing your work, **open a pull request** and provide a description of your changes. Ideally, your PR should reference an issue that explains the problem you are addressing.

All checks run automatically on every pull request for all supported PHP versions. For more details, see the relevant [workflows][1].

[1]: .github/workflows
