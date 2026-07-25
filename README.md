# php-progress

[![CI](https://github.com/konradmichalik/php-progress/actions/workflows/ci.yml/badge.svg)](https://github.com/konradmichalik/php-progress/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

Modern, dependency-free CLI progress bars & spinners for PHP 8.1+.

Smooth sub-cell rendering (⅛-block steps), truecolor gradients with shimmer, wall-clock animation, and a **single dynamic live line** that reflows as extra information appears and disappears — inspired by Rich, tqdm and ora rather than by classic PHP progress bars.

```
ver.di Migration  █████████▍────   67%  337/500  file /var/www…intro.mp4
⠹ Fetching schema  host staging.example.org  (0:04)
```

## Why

- **One engine, two presets.** A bar and a spinner are not different components — both are column sets over the same live line. Anything you can do with one works with the other.
- **Dynamic extras.** `set('file', $path)` makes info appear on the line, `clear('file')` makes it vanish; the line reflows. Under width pressure the layout degrades gracefully (label shrinks, low-priority columns drop) instead of wrapping or jittering.
- **Wall-clock animation.** Spinner frames, gradient shimmer and the indeterminate pulse are derived from `microtime()`, never from your update frequency — animation stays smooth whether you call `advance()` 10 or 10,000 times per second.
- **Honest degradation.** No TTY (CI, pipes, cron)? You get throttled plain-text status lines instead of ANSI noise. `NO_COLOR`, 256-color, basic-color and non-UTF-8 terminals are all handled.
- **Zero dependencies.** `ext-mbstring` only. Integrates with Symfony/TYPO3 commands via a stream, not a framework bridge.

## Install

```bash
composer require konradmichalik/php-progress
```

## Usage

### Progress bar

```php
use KonradMichalik\PhpProgress\Progress;

$bar = Progress::bar(total: 500, label: 'Content Migration');
// no ->start() needed -- the line appears on the first advance()/set()/tick()

foreach ($records as $record) {
    $bar->set('table', $record->table);   // appears on the line
    migrate($record);
    $bar->advance();
}

$bar->clear('table');                     // disappears, line reflows
$bar->finish('Migration complete');       // ✔ persists
```

Default columns: `label, bar, percent, count, fields, rate, eta`. Pick your own:

```php
Progress::bar(100, 'Upload')
    ->columns('label', 'bar', 'percent', 'fields', 'elapsed')
    ->unit('bytes')          // count/rate render as 3.2 MB / 1.1 MB/s
    ->barWidth(30)           // or ->expand() to fill the terminal
    ->transient()            // clear the line on finish instead of persisting
    ->handleSignals(false)   // opt out if your app manages SIGINT/SIGTERM itself
    ->start();
```

### Spinner

```php
$sp = Progress::spinner('Connecting')->style('dots')->start();   // explicit start is optional

$sp->set('host', $host);          // extra info appears
$sp->text('Fetching schema');     // swap the label
$sp->clear('host');               // extra info disappears

$sp->succeed('Schema up to date');   // ✔  (also: fail(), warn(), stop())
```

Custom spinner (cli-spinners compatible — frames + interval):

```php
Progress::spinner('Working')->frames(['◐', '◓', '◑', '◒'], intervalMs: 120)->color('#7c9cff');
```

Built-in styles: `dots`, `dots2`, `line`, `arc`, `circleHalves`, `moon`, `point`, `star`.

The `star` style is a Claude Code-style twinkle — a single mark pulsing
`·　✢　✳　✶　✻　✽` and back through asterisk dingbats of increasing weight. Pair it
with the terracotta brand colour for the full look; on non-UTF-8 terminals it
falls back to an ASCII `.　+　*` twinkle automatically:

```php
Progress::spinner('Cogitating')->style('star')->color('#d97757');
```

#### Procedural spinners

The liveliest patterns — a travelling equalizer, a comet with a fading tail —
are awkward as fixed frame lists. Two styles are computed per frame from a
wall-clock phase instead (the same technique the bar uses for shimmer and
pulse): `wave` and `comet`.

```php
Progress::spinner('Streaming')->style('wave');
Progress::spinner('Warming caches')->style('comet')->color('#22d3ee');
```

You can supply your own: a closure `(float $phase, Frame $frame): string` where
`$phase` runs 0→1 over `periodMs`. Return a string (ANSI colour allowed) whose
plain width stays constant across phases.

```php
use KonradMichalik\PhpProgress\Frame;

Progress::spinner('Loading')->spinnerFn(function (float $phase, Frame $f): string {
    $n = (int) round($phase * 3) % 4;
    return str_repeat('=', $n) . '>' . str_repeat(' ', 3 - $n);
}, periodMs: 500);
```

Both built-in procedural styles fall back to `line` on non-UTF-8 terminals; the
comet's shade-based tail (`█▓▒░`) fades even without colour.

### Iteration wrapper (tqdm-style)

```php
foreach (Progress::track($items, 'Indexing') as $item) {
    index($item);   // counting, rendering, finishing: automatic
}
```

### Unknown total

```php
$live = Progress::bar(null, 'Streaming')->unit('bytes')->start();
$live->progress($bytesSoFar);   // bar renders a travelling pulse + throughput
```

### Runtime fields & sticky

Fields drop responsively on narrow terminals (lowest-priority columns go first).
Mark a field `sticky` and it is never dropped — other columns yield instead:

```php
$bar->set('mode', 'IRRE', sticky: true);
```

### Log lines above the live line

```php
$bar->println('note: 3 orphaned child records skipped');
```

### External processes (rsync, mysqldump | pv, ...)

The poll loop doubles as the render tick, so the line stays animated even
while the child process is silent:

```php
Progress::process(['rsync', '-a', '--info=progress2', '--no-i-r', $src, $dst])
    ->label('rsync media')
    ->parse(fn (string $line) => preg_match('/(\d+)%/', $line, $m) ? (float) $m[1] : null)
    ->run();   // returns the exit code; non-zero renders ✖ automatically

// stderr-based progress (pv -n):
Progress::process('mysqldump db | pv -n -s ' . $estBytes . ' | gzip > dump.sql.gz')
    ->from('stderr')
    ->parse(fn ($l) => is_numeric(trim($l)) ? (float) $l : null)
    ->run();
```

`\r`-updated output (rsync!) is handled: lines are split on `\r` and `\n`.

### Wrap a unit of work

```php
Progress::spinner('Deploying')->run(fn () => deploy());
// ✔ on return, ✖ + rethrow on exception
```

### Symfony / TYPO3 commands

No bridge needed — `to()` accepts a stream or any object exposing `getStream()`
(Symfony's `StreamOutput`, hence TYPO3 command output):

```php
$bar = Progress::bar(500, 'Import')->to($output)->start();
```

php-progress writes to `STDERR` by default, so your command's stdout stays pipeable.

### Teardown safety

A live line never gets stuck: a forgotten `finish()`, an uncaught exception, or a
`break` out of `Progress::track()` all restore the cursor and close the line (the
generator's `finally` and the handle's destructor act as safety nets). Terminal
resizes are picked up while running (≤ 2s lag).

## Architecture

Five layers, strictly separated:

| Layer | Role |
|---|---|
| **Terminal** | Capability detection (TTY, width, color depth, unicode), ANSI/OSC writer |
| **State** | `Task` (total, completed, fields, status) + sliding-window rate/ETA estimator — knows nothing about rendering |
| **Segments** | Pure functions `(Task, Frame) → Chunk`; every animation phase derives from the wall clock carried in `Frame` |
| **Layout** | Single-line reflow: fixed segments measured first, the bar absorbs the remainder; under pressure segments *degrade* (label shrinks, fields → sticky-only) before being *dropped*, lowest priority first; essentials never drop |
| **Renderer** | FPS-throttled repaint-in-place, OSC 9;4 taskbar progress, cursor safety via shutdown handler — or throttled plain-text lines when there is no TTY |

Segments implement one small interface, so a custom column is ~20 lines —
and plugs straight into the line via `->columns('label', new MySegment(), 'bar', 'percent')`:

```php
interface Segment {
    public function key(): string;
    public function priority(): int;                 // higher survives longer
    public function canDegrade(int $level): bool;    // shrink before dropping
    public function render(Task $t, Frame $f, int $level): ?Chunk;  // null = absent
}
```

`Chunk` carries a plain twin (for measuring) and a styled twin (for output),
so width math is always exact regardless of ANSI styling.

### Rendering internals

- **Flicker-free repaint.** Each frame returns to column 0 and overwrites in
  place, erasing only the trailing overhang when the new line is shorter —
  rather than blanking the line first. This removes the erase-then-write flash
  visible on slow terminals and over SSH.
- **Terminal safety on interrupt.** A default Ctrl+C terminates the process
  without running shutdown functions, which would leak the hidden cursor. When
  `ext-pcntl` is present php-progress catches SIGINT/SIGTERM, restores the cursor and
  clears the taskbar progress, then re-raises with the default disposition so
  the exit status is preserved. Opt out via `->handleSignals(false)` if your
  application installs its own handlers.

## Degradation matrix

| Environment | Behaviour |
|---|---|
| Truecolor TTY | Gradient + shimmer, ⅛-block sub-cell bar, OSC 9;4 |
| 256 / basic colors | Colors quantized automatically |
| `NO_COLOR` | Structure without styling |
| Non-UTF-8 locale | ASCII theme (`#`/`-`, `OK`/`FAIL`, line spinner) |
| No TTY (CI, pipe) | Plain status lines, throttled to ~2s or every 10% |
| Narrow terminal | Columns degrade/drop by priority; sticky fields survive |

## Security

- **Terminal-injection safe by default.** Labels, field values and `println()`
  messages often come from untrusted sources — filenames, remote data, or a
  subprocess's own output via `Proc::onLine()`. All display text is passed
  through `Text::sanitize()` before it reaches the terminal: invalid UTF-8 is
  scrubbed and every escape sequence and control byte (`\r`, `\e]0;…\a`,
  `\e[2J`, BEL, backspace, …) is removed, so a hostile value cannot corrupt the
  line, forge log entries, or drive the terminal (title changes, screen clears).
  `Text::sanitize()` is public if you want it elsewhere.
- **No shell unless you ask for one.** `Progress::process([...])` (array form)
  executes the binary directly — no `/bin/sh`, so dynamic arguments can never be
  interpreted as shell. Use it for anything involving untrusted input. The
  string form (`Progress::process('a | b')`) exists for pipelines and runs via
  `/bin/sh -c`; never interpolate untrusted data into it.
- **Bounded memory.** The rate estimator coalesces high-frequency updates
  (O(1), ~100 samples max) and `Proc` retains only the last 64 KiB of each
  stream, so neither a tight `advance()` loop nor a chatty child process grows
  memory without bound.

## Development

```bash
composer install
composer test       # deterministic frame-capture suite (injected clock + fake caps)
composer analyse    # PHPStan, level max
composer check      # both
```

### Try it in a real terminal

`examples/showcase.php` is an interactive playground — run it in a real
terminal to see colour and animation for every variant:

```bash
php examples/showcase.php              # interactive menu
php examples/showcase.php all          # run every section back to back
php examples/showcase.php spinners     # a single section
php examples/showcase.php bars fields  # several sections

PROGRESS_SPEED=0.5 php examples/showcase.php all   # scale all timings (faster)
```

Sections: `spinners`, `bars`, `fields`, `reflow`, `degrade`, `process`,
`track`, `endstates`. The `reflow` and `degrade` sections render the same
frame across shrinking widths and colour tiers so you can see the graceful
degradation without resizing anything.


The test suite injects a deterministic clock and simulated terminal
capabilities, then asserts on captured frames — width discipline across
terminal sizes, reflow priorities, sticky behaviour, anti-jitter, non-TTY
output, and the process runner including failure propagation. CI runs the
suite on PHP 8.1–8.4 plus PHPStan at level max.

## License

Copyright © 2026 Konrad Michalik.

Licensed under the [GNU General Public License v3.0 or later](LICENSE) (`GPL-3.0-or-later`).
