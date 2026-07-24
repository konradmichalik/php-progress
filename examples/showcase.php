<?php

declare(strict_types=1);

/**
 * Interactive showcase — run in a REAL terminal to see colour & animation.
 *
 *   php examples/showcase.php              # interactive menu
 *   php examples/showcase.php all          # run every section back to back
 *   php examples/showcase.php spinners     # run one section
 *   php examples/showcase.php bars fields  # run several sections
 *
 * Sections: spinners bars fields reflow degrade process track endstates
 *
 * Tip: set PROGRESS_SPEED to scale all timings (e.g. PROGRESS_SPEED=0.5 = twice as fast).
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'KonradMichalik\\PhpProgress\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Progress;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

const SPEED = 1.0; // overridden below from env

$speed = (float) (getenv('PROGRESS_SPEED') ?: SPEED);
$speed = $speed > 0 ? $speed : 1.0;

/** Sleep in milliseconds, scaled by PROGRESS_SPEED. */
function nap(int $ms): void
{
    global $speed;
    usleep((int) ($ms * 1000 * $speed));
}

/** Animate a spinner/indeterminate live handle for a while, then hand it back. */
function spin(Live $live, int $ms): Live
{
    global $speed;
    $deadline = microtime(true) + ($ms / 1000) * $speed;
    while (microtime(true) < $deadline) {
        $live->tick();
        usleep(30000);
    }

    return $live;
}

/** Drive a determinate bar from 0..total over roughly $ms milliseconds. */
function fill(Live $bar, int $total, int $ms, ?callable $onStep = null): void
{
    global $speed;
    $per = (int) max(1, ($ms * $speed * 1000) / $total);
    for ($i = 1; $i <= $total; $i++) {
        if ($onStep !== null) {
            $onStep($bar, $i);
        }
        $bar->advance();
        usleep($per);
    }
}

function h(string $title): void
{
    echo "\n\033[1m\033[38;2;129;140;248m▸ {$title}\033[0m\n";
}

function note(string $s): void
{
    echo "  \033[2m{$s}\033[0m\n";
}

// --- sections ---------------------------------------------------------------

function section_spinners(): void
{
    h('Spinners — frame-based styles');
    foreach (['dots', 'dots2', 'line', 'arc', 'circleHalves', 'moon', 'point'] as $style) {
        $sp = Progress::spinner("style: {$style}")->style($style)->start();
        spin($sp, 1200)->succeed("style: {$style}");
    }

    h('Spinners — Claude Code-style star (terracotta)');
    $sp = Progress::spinner('Cogitating')->style('star')->color('#d97757')->start();
    spin($sp, 1600)->succeed('star');

    h('Spinners — procedural (computed, not enumerated)');
    $wave = Progress::spinner('wave (equalizer)')->style('wave')->start();
    spin($wave, 1600)->succeed('wave');
    $comet = Progress::spinner('comet (flying tail)')->style('comet')->color('#22d3ee')->start();
    spin($comet, 1600)->succeed('comet');

    h('Spinners — your own procedural closure via spinnerFn()');
    $loader = Progress::spinner('custom')->spinnerFn(static function (float $phase, Frame $f): string {
        $n = (int) round($phase * 3) % 4;
        return '[' . str_repeat('=', $n) . '>' . str_repeat(' ', 3 - $n) . ']';
    }, periodMs: 500)->start();
    spin($loader, 1600)->succeed('custom spinnerFn');
}

function section_bars(): void
{
    h('Bar — default columns (label, bar, percent, count, fields, rate, eta)');
    $bar = Progress::bar(200, 'Processing records')->start();
    fill($bar, 200, 2500);
    $bar->finish('Done');

    h('Bar — byte units (transfer)');
    $bar = Progress::bar(8_000_000, 'Downloading')->unit('bytes')
        ->columns('label', 'bar', 'percent', 'count', 'rate', 'eta')->start();
    for ($i = 0; $i < 80; $i++) { $bar->advance(100_000); nap(30); }
    $bar->finish('Downloaded');

    h('Bar — expand to full width + custom columns');
    $bar = Progress::bar(120, 'Indexing')->expand()->columns('label', 'bar', 'percent')->start();
    fill($bar, 120, 2000);
    $bar->finish();

    h('Bar — custom theme (warm gradient)');
    $theme = new Theme(gradientFrom: '#f97316', gradientTo: '#ef4444', accent: '#f97316');
    $bar = Progress::bar(120, 'Rendering')->theme($theme)->start();
    fill($bar, 120, 2000);
    $bar->finish();

    h('Bar — indeterminate (unknown total → travelling pulse)');
    $bar = Progress::bar(null, 'Streaming')->columns('label', 'bar', 'elapsed')->start();
    spin($bar, 2500)->succeed('Stream closed');
}

function section_fields(): void
{
    h('Fields — appear, change and disappear on a live line');
    $bar = Progress::bar(300, 'Migration')->start();
    fill($bar, 300, 4000, static function (Live $b, int $i): void {
        if ($i === 60)  { $b->set('table', 'tt_content'); }
        if ($i === 150) { $b->set('table', 'tx_site_domain_model_item'); $b->set('mode', 'IRRE', sticky: true); }
        if ($i === 150) { $b->println('note: 3 orphaned child records skipped'); }
        if ($i === 250) { $b->clear('table'); }
    });
    $bar->finish('Migration complete');
}

function section_reflow(): void
{
    h('Reflow — same bar at shrinking widths (columns degrade, then drop)');
    note('sticky "mode" survives; eta/rate/count drop first; percent & bar stay');
    foreach ([90, 64, 46, 34, 26] as $w) {
        $caps = new Capabilities(true, $w, 'truecolor', true);
        $bar = Progress::bar(100, 'A rather long migration label')
            ->caps($caps)->to(STDOUT)->start();
        $bar->set('mode', 'IRRE', sticky: true);
        $bar->set('file', '/var/www/app/fileadmin/media/intro.mp4');
        $bar->progress(67);
        $bar->tick();
        $bar->stop();
        echo "  \033[2m^ width {$w}\033[0m\n";
        nap(250);
    }
}

function section_degrade(): void
{
    h('Colour degradation — same frame across capability tiers');
    $tiers = [
        'truecolor' => new Capabilities(true, 64, 'truecolor', true),
        '256 color' => new Capabilities(true, 64, '256', true),
        'basic'     => new Capabilities(true, 64, 'basic', true),
        'NO_COLOR'  => new Capabilities(true, 64, 'none', true),
        'ASCII (non-UTF-8)' => new Capabilities(true, 64, 'none', false),
    ];
    foreach ($tiers as $name => $caps) {
        $theme = $caps->unicode ? null : Theme::ascii();
        $bar = Progress::bar(100, 'render')->caps($caps)->to(STDOUT);
        if ($theme !== null) { $bar->theme($theme); }
        $bar->start();
        $bar->progress(62);
        $bar->tick();
        $bar->stop();
        echo "  \033[2m^ {$name}\033[0m\n";
        nap(250);
    }
}

function section_process(): void
{
    h('Process — a child process drives the bar (fake rsync)');
    Progress::process(['bash', '-c', 'for p in 4 17 33 51 68 84 100; do echo "$p%"; sleep 0.25; done'])
        ->label('rsync media')
        ->parse(static fn (string $l) => preg_match('/^(\d+)%/', $l, $m) ? (float) $m[1] : null)
        ->onLine(static function (Live $live, string $stream, string $line): void {
            if (preg_match('/^(\d+)%/', $line)) { $live->set('phase', trim($line)); }
        })
        ->run();

    h('Process — spinner wrapping a child with unknown progress');
    Progress::process(['bash', '-c', 'sleep 2; echo ok'])->label('warming up')->run();

    h('Process — non-zero exit renders a failure state');
    Progress::process(['bash', '-c', 'echo "boom" >&2; sleep 1; exit 3'])->label('doomed task')->run();
}

function section_track(): void
{
    h('track() — zero-ceremony iteration wrapper (tqdm-style)');
    $sum = 0;
    foreach (Progress::track(range(1, 60), 'Summing') as $n) {
        $sum += $n;
        nap(25);
    }
    note("result: sum(1..60) = {$sum}");
}

function section_endstates(): void
{
    h('End states — succeed / fail / warn / stop');
    spin(Progress::spinner('task a')->start(), 700)->succeed('succeeded');
    spin(Progress::spinner('task b')->start(), 700)->fail('failed');
    spin(Progress::spinner('task c')->start(), 700)->warn('warning');
    spin(Progress::spinner('task d')->start(), 700)->stop('stopped');
}

// --- dispatch ---------------------------------------------------------------

$sections = [
    'spinners'  => 'section_spinners',
    'bars'      => 'section_bars',
    'fields'    => 'section_fields',
    'reflow'    => 'section_reflow',
    'degrade'   => 'section_degrade',
    'process'   => 'section_process',
    'track'     => 'section_track',
    'endstates' => 'section_endstates',
];

function run_sections(array $names, array $sections): void
{
    foreach ($names as $name) {
        if (isset($sections[$name])) {
            ($sections[$name])();
        } else {
            echo "  unknown section: {$name}\n";
        }
    }
    echo "\n\033[2mdone.\033[0m\n";
}

$args = \array_slice($argv, 1);

if ($args !== []) {
    if (\in_array('all', $args, true)) {
        run_sections(array_keys($sections), $sections);
    } elseif (\in_array('--help', $args, true) || \in_array('-h', $args, true)) {
        echo "Sections: " . implode(' ', array_keys($sections)) . " all\n";
    } else {
        run_sections($args, $sections);
    }
    exit(0);
}

// No args: interactive menu when we have a real terminal on STDIN.
if (!@stream_isatty(STDIN)) {
    echo "Usage: php examples/showcase.php [all|" . implode('|', array_keys($sections)) . "] ...\n";
    exit(0);
}

$keys = array_keys($sections);
while (true) {
    echo "\n\033[1mphp-progress showcase\033[0m  \033[2m(real terminal recommended)\033[0m\n";
    foreach ($keys as $i => $name) {
        printf("  %d) %s\n", $i + 1, $name);
    }
    echo "  a) all\n  q) quit\n> ";

    $line = fgets(STDIN);
    if ($line === false) {
        break;
    }
    $choice = trim($line);
    if ($choice === 'q' || $choice === 'quit') {
        break;
    }
    if ($choice === 'a' || $choice === 'all') {
        run_sections($keys, $sections);
        continue;
    }
    if (ctype_digit($choice) && isset($keys[(int) $choice - 1])) {
        run_sections([$keys[(int) $choice - 1]], $sections);
        continue;
    }
    if (isset($sections[$choice])) {
        run_sections([$choice], $sections);
        continue;
    }
    echo "  ?\n";
}
echo "bye.\n";
