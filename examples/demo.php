<?php

declare(strict_types=1);

// Run: php examples/demo.php   (best in a real terminal; degrades gracefully in CI/pipes)

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'KonradMichalik\\PhpProgress\\')) {
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, \strlen('KonradMichalik\\PhpProgress\\'))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

use KonradMichalik\PhpProgress\Progress;

// 1) Determinate bar with runtime fields that appear and disappear
$bar = Progress::bar(total: 240, label: 'Content Migration')->start();
foreach (range(1, 240) as $i) {
    usleep(12000);
    if ($i === 40) {
        $bar->set('table', 'tt_content');
    }
    if ($i === 120) {
        $bar->set('table', 'tx_site_domain_model_item');
        $bar->set('mode', 'IRRE', sticky: true);
    }
    if ($i === 200) {
        $bar->clear('table');
    }
    if ($i === 150) {
        $bar->println('note: 3 orphaned child records skipped');
    }
    $bar->advance();
}
$bar->finish('Migration complete');

// 2) Spinner with phases and final states (note: no ->start() -- lazy)
$sp = Progress::spinner('Connecting to staging')->style('dots');
usleep(600000);
$sp->set('host', 'staging.example.org');
usleep(500000);
$sp->text('Fetching schema');
usleep(600000);
$sp->clear('host');
$sp->succeed('Schema up to date');

// 2b) Claude Code-style twinkling star, terracotta brand colour
$star = Progress::spinner('Cogitating')->style('star')->color('#d97757');
foreach (['Cogitating', 'Combobulating', 'Crystallizing', 'Finalizing'] as $verb) {
    $star->text($verb);
    usleep(700000);
}
$star->succeed('Thought complete');

// 2c) Procedural spinners: wave (equalizer) and comet (flying tail), computed
//     from the wall clock rather than enumerated as frames.
$wave = Progress::spinner('Streaming events')->style('wave');
usleep(1500000);
$wave->succeed('Stream drained');

$comet = Progress::spinner('Warming caches')->style('comet')->color('#22d3ee');
usleep(1500000);
$comet->succeed('Caches hot');

// 2d) Your own procedural spinner: a phase-driven closure
$loader = Progress::spinner('Loading')->spinnerFn(static function (float $phase, $frame): string {
    $n = (int) round($phase * 3) % 4;
    return str_repeat('=', $n) . '>' . str_repeat(' ', 3 - $n);
}, periodMs: 500);
usleep(1500000);
$loader->succeed('Loaded');

// 3) Unknown total: pulse + throughput
$stream = Progress::bar(null, 'Streaming rows')->unit('bytes')->start();
$sent = 0;
for ($i = 0; $i < 60; $i++) {
    usleep(25000);
    $sent += random_int(20_000, 90_000);
    $stream->progress($sent);
}
$stream->finish('Stream closed');

// 4) Zero-ceremony iteration
$items = range(1, 80);
foreach (Progress::track($items, 'Indexing') as $item) {
    usleep(8000);
}

// 5) External process driving the bar (fake rsync)
Progress::process(['bash', '-c', 'for p in 5 18 34 52 71 88 100; do echo "$p%"; sleep 0.2; done'])
    ->label('rsync media')
    ->parse(static fn (string $line) => preg_match('/^(\d+)%/', $line, $m) ? (float) $m[1] : null)
    ->run();

// 6) Wrap a unit of work: auto success/failure
Progress::spinner('Cleanup')->run(static fn () => usleep(400000));

echo "done.\n";
