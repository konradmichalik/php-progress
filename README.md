<div align="center">

# Progress

[![CI](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-progress/ci.yml?label=ci&logo=github)](https://github.com/konradmichalik/php-progress/actions/workflows/ci.yml)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/php-progress/php?logo=php)](https://packagist.org/packages/konradmichalik/php-progress)
[![License](https://img.shields.io/packagist/l/konradmichalik/php-progress)](LICENSE)

</div>

A modern, **framework-agnostic** CLI progress bar & spinner library for PHP.
Smooth sub-cell rendering, truecolor gradients with shimmer, wall-clock animation
and a single dynamic line that reflows as information appears and disappears —
inspired by Rich, tqdm and ora. No runtime dependencies beyond `ext-mbstring`.

```
ver.di Migration  █████████▍────   67%  337/500  file /var/www…intro.mp4
⠹ Fetching schema  host staging.example.org  (0:04)
```

## 🚀 Features

* **One engine, two presets** — a bar and a spinner are just column sets over the same live line
* **Sub-cell smooth bar** — ⅛-block steps, truecolor gradient with shimmer, indeterminate pulse
* **Dynamic fields** — `set()` / `clear()` make info appear and vanish; the line reflows, sticky fields survive
* **Rich spinners** — frame styles (`dots`, `arc`, `star`, …), procedural `wave` / `comet`, or your own closure
* **Wall-clock animation** — stays smooth whether you update 10 or 10,000 times per second
* **Honest degradation** — 256/basic/`NO_COLOR`, ASCII on non-UTF-8 terminals, plain log lines without a TTY
* **Batteries included** — `track()` iterator, external-process runner, Symfony/TYPO3 output
* **Terminal-injection safe** — untrusted labels, fields and log lines are sanitized before they reach the terminal

## 🔥 Installation

```bash
composer require konradmichalik/php-progress
```

## ⚡ Usage

### Progress bar

```php
use KonradMichalik\PhpProgress\Progress;

$bar = Progress::bar(total: 500, label: 'Content Migration');
// no ->start() needed — the line appears on the first advance()/set()/tick()

foreach ($records as $record) {
    $bar->set('table', $record->table);   // extra info appears on the line
    migrate($record);
    $bar->advance();
}

$bar->clear('table');                      // disappears, line reflows
$bar->finish('Migration complete');        // ✔ persists
```

Default columns: `label, bar, percent, count, fields, rate, eta`. Pick your own and tune the bar:

```php
$bar = Progress::bar(100, 'Upload')
    ->columns('label', 'bar', 'percent', 'fields', 'elapsed')
    ->unit('bytes')          // count/rate render as 3.2 MB / 1.1 MB/s
    ->barWidth(30)           // or ->expand() to fill the terminal
    ->transient();           // clear the line on finish instead of persisting

$bar->set('mode', 'IRRE', sticky: true);   // sticky fields are never dropped under width pressure
```

### Spinner

```php
$sp = Progress::spinner('Connecting')->style('dots');

$sp->set('host', $host);          // extra info appears
$sp->text('Fetching schema');     // swap the label
$sp->clear('host');

$sp->succeed('Schema up to date');   // ✔  (also: fail(), warn(), stop())
```

Built-in styles: `dots`, `dots2`, `line`, `arc`, `circleHalves`, `moon`, `point`, `star`
(a Claude Code-style twinkle) plus the procedural `wave` and `comet`. Bring your own —
frames or a phase-driven closure:

```php
use KonradMichalik\PhpProgress\Frame;

Progress::spinner('Working')->frames(['◐', '◓', '◑', '◒'], intervalMs: 120)->color('#7c9cff');

Progress::spinner('Loading')->spinnerFn(function (float $phase, Frame $f): string {
    $n = (int) round($phase * 3) % 4;
    return str_repeat('=', $n) . '>' . str_repeat(' ', 3 - $n);
}, periodMs: 500);
```

### Iteration wrapper

```php
foreach (Progress::track($items, 'Indexing') as $item) {
    index($item);   // counting, rendering, finishing: automatic
}
```

### External processes

The poll loop doubles as the render tick, so the line stays animated even while the
child is silent. `\r`-updated output (rsync!) is handled:

```php
Progress::process(['rsync', '-a', '--info=progress2', '--no-i-r', $src, $dst])
    ->label('rsync media')
    ->parse(fn (string $line) => preg_match('/(\d+)%/', $line, $m) ? (float) $m[1] : null)
    ->run();   // returns the exit code; non-zero renders ✖ automatically
```

The array form runs the binary directly (no shell) — use it for untrusted input.
The string form (`Progress::process('a | b')`) runs via `/bin/sh -c` for pipelines.

### Wrap a unit of work

```php
Progress::spinner('Deploying')->run(fn () => deploy());   // ✔ on return, ✖ + rethrow on exception
```

### Symfony / TYPO3

No bridge needed — `to()` accepts a stream or any object exposing `getStream()`
(Symfony's `StreamOutput`, hence TYPO3 command output). php-progress writes to
`STDERR` by default, so your command's stdout stays pipeable:

```php
$bar = Progress::bar(500, 'Import')->to($output)->start();
```

## 🖥️ Degradation

| Environment | Behaviour |
|---|---|
| Truecolor TTY | Gradient + shimmer, ⅛-block sub-cell bar, OSC 9;4 taskbar progress |
| 256 / basic / `NO_COLOR` | Colors quantized or dropped, structure preserved |
| Non-UTF-8 locale | ASCII theme (`#`/`-`, `OK`/`FAIL`, line spinner) |
| No TTY (CI, pipe) | Plain status lines, throttled to ~2s or every 10% |
| Narrow terminal | Columns degrade/drop by priority; sticky fields survive |

A live line never gets stuck: a forgotten `finish()`, an uncaught exception or a
`break` out of `track()` all restore the cursor (via the generator's `finally`, the
handle's destructor and, with `ext-pcntl`, a SIGINT/SIGTERM handler).

## 🧑‍💻 Contributing

Issues and pull requests are welcome.

```bash
composer test       # deterministic frame-capture suite (injected clock + fake caps)
composer analyse    # PHPStan, level max
composer check      # both

php examples/showcase.php all   # see colour & animation in a real terminal
```

## 📄 License

This project is licensed under [GPL-3.0-or-later](LICENSE).
