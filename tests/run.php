<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use KonradMichalik\PhpProgress\Progress;
use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

$tty = static fn (int $width): Capabilities => new Capabilities(true, $width, 'truecolor', true);

echo "-- text utils\n";
assert_true(Text::truncateMiddle('/var/www/project/deploy/dump.sql.gz', 20) !== '' , 'truncateMiddle runs');
$t = Text::truncateMiddle('/var/www/project/deploy/dump.sql.gz', 20);
assert_true(Text::width($t) <= 20, "truncateMiddle respects width ({$t})");
assert_true(str_contains($t, '…'), 'truncateMiddle inserts ellipsis');
assert_true(str_starts_with($t, '/var') && str_ends_with($t, '.gz'), "path-aware: keeps both ends ({$t})");
assert_true(Text::duration(3723) === '1:02:03', 'duration h:mm:ss');
assert_true(Text::bytes(1536) === '1.5 KB', 'bytes humanize');

echo "-- bar rendering, width discipline across terminal sizes\n";
foreach ([100, 64, 40, 30, 24] as $width) {
    [$stream, $read] = memStream();
    $ms = 0.0;
    $bar = Live::bar(100, 'Content Migration ver.di')
        ->to($stream)->caps($tty($width))->clock(function () use (&$ms) { return $ms; })
        ->start();
    for ($i = 0; $i < 100; $i++) {
        $ms += 120;
        $bar->advance();
        if ($i === 30) { $bar->set('table', 'tx_sitepackage_domain_model_item'); }
        if ($i === 60) { $bar->clear('table'); }
    }
    $bar->finish();
    $all = frames($read());
    assert_true(count($all) > 5, "width {$width}: multiple frames rendered");
    $max = 0;
    foreach ($all as $f) {
        $max = max($max, Text::width(rtrim($f, "\n")));
    }
    assert_true($max <= $width, "width {$width}: no frame exceeds terminal ({$max})");
}

echo "-- fields appear and disappear\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(10, 'Sync')->to($stream)->caps($tty(100))->clock(function () use (&$ms) { return $ms; })->start();
$ms += 200; $bar->advance();
$ms += 200; $bar->set('file', 'assets/video/intro.mp4');
$ms += 200; $bar->advance();
$ms += 200; $bar->clear('file');
$ms += 200; $bar->advance();
$bar->finish();
$all = frames($read());
$withField = array_filter($all, static fn ($f) => str_contains($f, 'intro.mp4'));
assert_true($withField !== [], 'field value appears on the line');
$last = end($all);
assert_true(!str_contains($last, 'intro.mp4'), 'cleared field is gone from final frame');

echo "-- reflow: narrow terminal drops eta/rate/count before percent\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(1000, 'A very long migration label that will not fit')
    ->to($stream)->caps($tty(34))->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 500; $i++) { $ms += 50; $bar->advance(); }
$framesN = frames($read());
$mid = $framesN[count($framesN) - 1];
assert_true(str_contains($mid, '%'), "34 cols: percent survives ({$mid})");
assert_true(!str_contains($mid, 'eta') && !str_contains($mid, 'it/s'), '34 cols: eta+rate dropped');
assert_true(!str_contains($mid, '/1000'), '34 cols: count dropped');

echo "-- reflow: bar reaches comfort width instead of starving while columns still hold space\n";
// Regression: the flex bar must not collapse toward its minWidth() while there
// is still slack to reclaim -- a droppable column (count) or an un-degraded
// label present means the layout should have fed the bar first. This pins the
// old 33 -> 7 (full label + count kept, bar starved) reflow bug at w=64.
$barWidth = static function (string $frame): int {
    // Bar alphabet: full block, 1/8..7/8 partials, and the track dash.
    return preg_match('/[\x{2500}\x{2588}-\x{258F}]+/u', $frame, $m) === 1
        ? \KonradMichalik\PhpProgress\Support\Text::width($m[0])
        : 0;
};
foreach ([90, 64, 46, 34, 26] as $w) {
    [$stream, $read] = memStream();
    $ms = 0.0;
    $bar = Live::bar(100, 'A rather long migration label')
        ->to($stream)->caps(new Capabilities(true, $w, 'none', true))
        ->clock(function () use (&$ms) { return $ms; })->start();
    $bar->set('mode', 'IRRE', sticky: true);
    $ms += 100; $bar->progress(67); $bar->tick();
    $all = frames($read());
    $last = rtrim((string) end($all));
    $bw = $barWidth($last);
    // 'migration label' only survives in the un-degraded label; count as '/100'.
    $hasSlack = str_contains($last, 'migration label') || str_contains($last, '/100');
    if ($hasSlack) {
        assert_true($bw >= 12, "width {$w}: bar at comfort (>=12) while slack remains ({$bw}: {$last})");
    }
}

echo "-- sticky fields survive width pressure\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(100, 'Import')->to($stream)->caps($tty(44))->clock(function () use (&$ms) { return $ms; })->start();
$bar->set('host', 'db01', sticky: true);
$bar->set('debug', 'irre-children-recheck-pass-2');
for ($i = 0; $i < 50; $i++) { $ms += 50; $bar->advance(); }
$all = frames($read());
$last = $all[count($all) - 1];
assert_true(str_contains($last, 'db01'), "44 cols: sticky field kept ({$last})");
assert_true(!str_contains($last, 'irre-children'), '44 cols: non-sticky field degraded away');

echo "-- percent has fixed width (anti-jitter)\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(100, 'x')->columns('bar', 'percent')->to($stream)->caps($tty(60))
    ->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 100; $i++) { $ms += 100; $bar->advance(); }
$bar->finish();
$widths = array_unique(array_map(static fn ($f) => Text::width(rtrim($f)), frames($read())));
assert_true(count($widths) === 1, 'line width constant from 1% to 100% (' . implode(',', $widths) . ')');

echo "-- spinner: frames animate from wall clock, uniform width, end icon\n";
[$stream, $read] = memStream();
$ms = 0.0;
$sp = Live::spinner('Connecting')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 12; $i++) { $ms += 90; $sp->tick(); }
$sp->set('host', 'staging.verdi-bb.de');
$ms += 90; $sp->tick();
$sp->succeed('Connected');
$all = frames($read());
$distinct = array_unique(array_map(static fn ($f) => mb_substr($f, 0, 1), array_slice($all, 0, 12)));
assert_true(count($distinct) > 3, 'spinner glyph advances with wall clock');
$final = end($all);
assert_true(str_contains($final, '✔') && str_contains($final, 'Connected'), "success icon + final text ({$final})");
assert_true((bool) array_filter($all, static fn ($f) => str_contains($f, 'bb.de')), 'spinner shows fields too');

echo "-- spinner: Claude Code-style star twinkle + ascii fallback\n";
[$stream, $read] = memStream();
$ms = 0.0;
$sp = Live::spinner('Cogitating')->style('star')->color('#d97757')
    ->to($stream)->caps($tty(72))->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 10; $i++) { $ms += 90; $sp->tick(); }
$sp->succeed('Done');
$all = frames($read());
$glyphs = [];
foreach ($all as $f) { $f = ltrim($f); if ($f !== '') { $glyphs[] = mb_substr($f, 0, 1); } }
$sparkle = array_intersect($glyphs, ['·', '✢', '✳', '✶', '✻', '✽']);
assert_true(count(array_unique($sparkle)) >= 4, 'star cycles through multiple sparkle glyphs (' . implode('', array_unique($sparkle)) . ')');
$maxw = 0;
foreach ($all as $f) { $maxw = max($maxw, Text::width(rtrim($f))); }
assert_true($maxw <= 72, 'star spinner keeps line within width');
// ASCII fallback on a non-UTF-8 terminal
[$stream, $read] = memStream();
$ms = 0.0;
$noU = new Capabilities(true, 72, 'truecolor', false);
$sp = Live::spinner('Working')->style('star')->to($stream)->caps($noU)->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 6; $i++) { $ms += 100; $sp->tick(); }
$sp->stop();
$raw = $read();
assert_true(preg_match('/[·✢✳✶✻✽]/u', $raw) !== 1, 'star ascii fallback emits no unicode sparkles');
assert_true(str_contains($raw, '*'), 'star ascii fallback uses asterisk mark');

echo "-- spinner: procedural wave + comet (computed, not enumerated)\n";
foreach (['wave' => 5, 'comet' => 9] as $style => $expectWidth) {
    [$stream, $read] = memStream();
    $ms = 0.0;
    $sp = Live::spinner('Working')->style($style)
        ->to($stream)->caps($tty(72))->clock(function () use (&$ms) { return $ms; })->start();
    $leads = [];
    for ($i = 0; $i < 12; $i++) { $ms += 100; $sp->tick(); }
    $sp->succeed('ok');
    $all = frames($read());
    $widths = [];
    foreach ($all as $f) {
        $f = rtrim($f);
        if ($f === '') { continue; }
        $lead = explode(' ', ltrim($f))[0];
        // ignore the final success-icon frame (spinner collapses to ✔)
        if ($lead === '✔') { continue; }
        $widths[Text::width($lead)] = true;
        $leads[] = $lead;
    }
    assert_true(array_keys($widths) === [$expectWidth], "{$style}: constant lead width {$expectWidth} (" . implode(',', array_keys($widths)) . ')');
    assert_true(count(array_unique($leads)) >= 4, "{$style}: animates over wall clock (" . count(array_unique($leads)) . ' distinct)');
}

echo "-- spinner: comet tail fades even without colour (NO_COLOR)\n";
[$stream, $read] = memStream();
$ms = 0.0;
$mono = new Capabilities(true, 72, 'none', true);
$sp = Live::spinner('x')->style('comet')->to($stream)->caps($mono)->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 6; $i++) { $ms += 110; $sp->tick(); }
$sp->stop();
$raw = $read();
assert_true(preg_match('/[▓▒░]/u', $raw) === 1, 'comet shows shade-trail glyphs in monochrome');

echo "-- spinner: procedural styles fall back to line on non-UTF-8\n";
[$stream, $read] = memStream();
$ms = 0.0;
$noU = new Capabilities(true, 72, 'truecolor', false);
$sp = Live::spinner('x')->style('wave')->to($stream)->caps($noU)->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 5; $i++) { $ms += 120; $sp->tick(); }
$sp->stop();
assert_true(preg_match('/[▁▂▃▄▅▆▇█]/u', $read()) !== 1, 'wave emits no block glyphs on non-UTF-8 terminal');

echo "-- spinner: custom procedural closure via spinnerFn()\n";
[$stream, $read] = memStream();
$ms = 0.0;
$fn = static function (float $phase, $frame): string {
    $n = (int) round($phase * 3) % 4;         // 0..3
    return str_repeat('=', $n) . '>' . str_repeat(' ', 3 - $n);
};
$sp = Live::spinner('Loading')->spinnerFn($fn, periodMs: 400)
    ->to($stream)->caps($tty(72))->clock(function () use (&$ms) { return $ms; })->start();
$seen = [];
for ($i = 0; $i < 8; $i++) { $ms += 100; $sp->tick(); $f = frames($read()); }
$sp->succeed('done');
$all = frames($read());
$leads = [];
foreach ($all as $f) { $f = rtrim($f); if ($f !== '' && !str_contains($f, '✔')) { $leads[] = mb_substr($f, 0, 4); } }
assert_true(count(array_unique($leads)) >= 2, 'custom spinnerFn animates from phase');

echo "-- indeterminate bar (pulse) when total unknown\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(null, 'Streaming')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 10; $i++) { $ms += 100; $bar->tick(); }
$bar->finish();
$all = frames($read());
assert_true(!str_contains(implode('', $all), '%'), 'no percent without total');
assert_true(count(array_unique(array_slice($all, 0, 10))) > 3, 'pulse animates over time');

echo "-- OSC 9;4 taskbar progress emitted\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(10, 'x')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; })->start();
$ms += 100; $bar->advance(5);
$bar->finish();
$raw = $read();
assert_true(str_contains($raw, "\e]9;4;1;"), 'determinate OSC 9;4 present');
assert_true(str_contains($raw, "\e]9;4;0;0\x07"), 'OSC cleared on finish');

echo "-- non-TTY: plain log lines, no ANSI\n";
[$stream, $read] = memStream();
$ms = 0.0;
$noTty = new Capabilities(false, 80, 'none', true);
$bar = Live::bar(100, 'CI import')->to($stream)->caps($noTty)->clock(function () use (&$ms) { return $ms; })->start();
for ($i = 0; $i < 100; $i++) { $ms += 50; $bar->advance(); }
$bar->finish();
$raw = $read();
assert_true(!str_contains($raw, "\e["), 'no ANSI codes in non-TTY output');
$lines = array_filter(explode("\n", $raw));
assert_true(count($lines) >= 5 && count($lines) <= 20, 'decile-throttled log lines (' . count($lines) . ')');
assert_true(str_contains($raw, '[ok] CI import 100% (100/100)'), 'final plain summary line');

echo "-- track() wrapper\n";
[$stream, $read] = memStream();
$sum = 0;
$trackLive = Live::bar(null, 'Sum')->to($stream)->caps($tty(60));
foreach (Progress::track(range(1, 5), 'Sum', live: $trackLive) as $n) { $sum += $n; }
assert_true($sum === 15, 'track() yields all items');

echo "-- println above live line\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(10, 'Work')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; })->start();
$ms += 100; $bar->advance();
$bar->println('warning: skipped record 42');
$bar->finish();
assert_true(str_contains($read(), "skipped record 42\n"), 'println interleaves log line');

echo "-- process runner: parses percent from child output\n";
[$stream, $read] = memStream();
$live = Live::bar(100.0, 'fake-rsync')->columns('label', 'bar', 'percent')
    ->to($stream)->caps($tty(60))->fps(1000.0);
$code = Progress::process(['bash', '-c', 'for i in 10 35 60 85 100; do echo "$i% done"; sleep 0.12; done'])
    ->parse(static fn (string $line) => preg_match('/^(\d+)%/', $line, $m) ? (float) $m[1] : null)
    ->live($live)
    ->run();
assert_true($code === 0, 'process exit code 0');
$all = frames($read());
$joined = implode("\n", $all);
assert_true(str_contains($joined, ' 60%') || str_contains($joined, ' 35%'), 'intermediate percent rendered');
assert_true(str_contains(end($all), '100%'), 'reaches 100%');

echo "-- process runner: failure state\n";
[$stream, $read] = memStream();
$live = Live::spinner('doomed')->to($stream)->caps($tty(60));
$code = Progress::process(['bash', '-c', 'echo boom >&2; exit 3'])->live($live)->run();
assert_true($code === 3, 'exit code propagated');
assert_true(str_contains(Text::stripAnsi($read()), '✖'), 'failure icon rendered');

echo "-- security: Text::sanitize neutralizes control chars & escape sequences\n";
assert_true(Text::sanitize("a\r\rb") === 'ab', 'sanitize strips carriage returns');
assert_true(Text::sanitize("x\e]0;title\x07y") === 'xy', 'sanitize strips OSC sequence');
assert_true(Text::sanitize("x\e[2J\e[1;1Hy") === 'xy', 'sanitize strips CSI sequences');
assert_true(Text::sanitize("tab\tnl\ndel\x7f") === 'tabnldel', 'sanitize strips tab/newline/DEL');
assert_true(Text::sanitize("plain/path.mp4") === 'plain/path.mp4', 'sanitize leaves printable text intact');
assert_true(mb_check_encoding(Text::sanitize("bad\xFF\xFEbytes"), 'UTF-8'), 'sanitize yields valid UTF-8');

echo "-- security: injected label/field cannot drive the terminal\n";
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(10, "sync \e]0;PWNED\x07 job")->to($stream)->caps($tty(80))
    ->clock(function () use (&$ms) { return $ms; })->start();
$bar->set('file', "a\r\rb\e[2Jc");   // e.g. a hostile filename fed through onLine()
$ms += 100; $bar->advance();
$bar->finish();
$raw = $read();
assert_true(!str_contains($raw, 'PWNED'), 'OSC title-injection payload removed from output');
assert_true(!str_contains($raw, ']0;'), 'OSC introducer removed');
assert_true(!str_contains($raw, '[2J'), 'CSI screen-clear removed');
$visible = frames($raw);
assert_true((bool) array_filter($visible, static fn ($f) => str_contains($f, 'abc')), 'field value survives as cleaned text (abc)');
$maxw = 0;
foreach ($visible as $f) { $maxw = max($maxw, Text::width(rtrim($f))); }
assert_true($maxw <= 80, 'injected control chars do not desync width accounting');

echo "-- security: Proc capture buffer is bounded\n";
[$stream, $read] = memStream();
$live = Live::bar(100.0, 'big')->columns('label', 'bar', 'percent')->to($stream)->caps($tty(60))->fps(1000.0);
$proc = Progress::process(['bash', '-c', 'head -c 200000 /dev/zero | tr "\0" "A"']);
$code = $proc->live($live)->run();
assert_true($code === 0, 'high-output process exits 0');
assert_true(strlen($proc->stdout) <= 65536, 'stdout capture bounded to tail (' . strlen($proc->stdout) . ' bytes)');

echo "-- label: final text shows even when the initial label was empty\n";
[$stream, $read] = memStream();
$ms = 0.0;
$sp = Live::spinner('')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; })->start();
$ms += 90; $sp->tick();
$sp->succeed('Schema up to date');
assert_true(str_contains(Text::stripAnsi($read()), 'Schema up to date'), 'empty-label spinner still shows final text');

echo "-- robustness: empty custom frames are rejected, not a fatal modulo\n";
$rejected = false;
try {
    Live::spinner('x')->frames([]);
} catch (\InvalidArgumentException) {
    $rejected = true;
}
assert_true($rejected, 'frames([]) throws InvalidArgumentException instead of DivisionByZeroError');

echo "-- reflow: line never exceeds terminal, even when undroppable columns don't fit\n";
foreach ([6, 9, 20, 24, 26] as $w) {
    [$stream, $read] = memStream();
    $ms = 0.0;
    $bar = Live::bar(100, '')->to($stream)->caps($tty($w))->clock(function () use (&$ms) { return $ms; })->start();
    $bar->set('mode', 'IRRE cascade delete children', sticky: true);   // undroppable + oversized
    $ms += 100; $bar->progress(50); $bar->tick();
    $max = 0;
    foreach (frames($read()) as $f) { $max = max($max, Text::width($f)); }
    assert_true($max <= $w, "width {$w}: sticky-field line clamped to terminal ({$max})");
}
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(100, '')->columns('bar', 'percent')->to($stream)->caps($tty(5))->clock(function () use (&$ms) { return $ms; })->start();
$ms += 100; $bar->progress(50); $bar->tick();
$max = 0;
foreach (frames($read()) as $f) { $max = max($max, Text::width($f)); }
assert_true($max <= 5, "width 5: bar+percent clamped to terminal ({$max})");

echo "-- security: Text::sanitize neutralizes 8-bit C1 control sequences\n";
assert_true(Text::sanitize("\u{009b}2J") === '', 'sanitize strips 8-bit CSI (U+009B) sequence');
assert_true(Text::sanitize("\u{009d}0;PWNED\u{009c}") === '', 'sanitize strips 8-bit OSC (U+009D) title injection');
assert_true(preg_match('/[\x{0080}-\x{009F}]/u', Text::sanitize("a\u{0090}\u{009e}\u{009f}b")) !== 1, 'no C1 control code point survives');
assert_true(Text::sanitize("plain/path.mp4") === 'plain/path.mp4', 'sanitize still leaves printable text intact');

echo "-- security: Text::clampAnsi keeps visible width within budget and closes colour\n";
$styled = "\e[38;2;1;2;3mABCDEFG\e[0m";
$clamped = Text::clampAnsi($styled, 3);
assert_true(Text::width(Text::stripAnsi($clamped)) === 3, 'clampAnsi cuts to exact visible width');
assert_true(str_ends_with($clamped, "\e[0m"), 'clampAnsi appends a reset so colour cannot bleed');
assert_true(Text::clampAnsi($styled, 99) === $styled, 'clampAnsi leaves a fitting line untouched');

echo "-- process runner: keeps the un-terminated overflow remainder (no lost bytes)\n";
[$stream, $read] = memStream();
$live = Live::spinner('x')->to($stream)->caps($tty(60))->fps(1000.0);
$seen = '';
Progress::process(['bash', '-c', 'printf "%1048576s" "" | tr " " A; printf "MARKER"; printf "%80s" "" | tr " " B; printf "\n"'])
    ->onLine(static function ($l, $stream, $line) use (&$seen) { $seen .= $line; })
    ->live($live)
    ->run();
assert_true(str_contains($seen, 'MARKER'), 'over-MAX_LINE remainder is preserved, not discarded');

echo "-- process runner: a throwing parse() callback still tears the child down\n";
[$stream, $read] = memStream();
$live = Live::bar(100.0, 'x')->to($stream)->caps($tty(60))->fps(1000.0);
$threw = false;
try {
    Progress::process(['bash', '-c', 'echo 1%; sleep 30'])
        ->parse(static fn (string $l) => str_contains($l, '1%') ? throw new RuntimeException('boom') : null)
        ->live($live)
        ->run();
} catch (RuntimeException) {
    $threw = true;
}
assert_true($threw, 'callback exception propagates out of run()');
if (stripos(PHP_OS, 'linux') === 0 && \function_exists('getmypid')) {
    usleep(200000);
    $kids = (string) @shell_exec('ps -eo ppid,comm | awk -v p=' . (int) getmypid() . ' \'$1==p\'');
    assert_true(!str_contains($kids, 'sleep') && !str_contains($kids, 'bash'), 'no child process leaks after the exception');
} else {
    echo "  skip  child-leak check (needs Linux + ps)\n";
}

echo "\nALL TESTS PASSED\n";

echo "-- integration: auto-start on first mutation (no ->start() needed)\n";
[$stream, $read] = memStream();
$ms = 0.0;
$lazy = Live::bar(10, 'Lazy')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; });
$ms += 100;
$lazy->advance();
assert_true(frames($read()) !== [], 'first advance() paints without explicit start()');
$lazy->finish();

echo "-- integration: destructor safety net (forgotten finish)\n";
[$stream, $read] = memStream();
$ms = 0.0;
$leaky = Live::bar(10, 'Leak')->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; });
$ms += 100;
$leaky->advance();
unset($leaky);
$raw = $read();
assert_true(str_contains($raw, "\e[?25h"), 'destructor restores cursor');
assert_true(str_contains($raw, "\e]9;4;0;0\x07"), 'destructor clears taskbar progress (full teardown ran)');

echo "-- integration: early break out of track() tears down cleanly\n";
[$stream, $read] = memStream();
$trackLive2 = Live::bar(null, 'Break')->to($stream)->caps($tty(60));
foreach (Progress::track(range(1, 10), 'Break', 10, $trackLive2) as $n) {
    if ($n === 3) {
        break;
    }
}
gc_collect_cycles();
$raw = $read();
assert_true(str_contains($raw, "\e[?25h"), 'break: cursor restored via generator finally');
assert_true($trackLive2->task()->status->finished(), 'break: task no longer running');

echo "-- integration: run() wraps work with auto succeed/fail\n";
[$stream, $read] = memStream();
$result = Live::spinner('Wrapped')->to($stream)->caps($tty(60))->run(static fn () => 42);
assert_true($result === 42, 'run() returns callable result');
assert_true(str_contains(Text::stripAnsi($read()), '✔'), 'run() auto-succeeds');
[$stream, $read] = memStream();
$threw = false;
try {
    Live::spinner('Doomed')->to($stream)->caps($tty(60))->run(static fn () => throw new RuntimeException('x'));
} catch (RuntimeException) {
    $threw = true;
}
assert_true($threw, 'run() rethrows');
assert_true(str_contains(Text::stripAnsi($read()), '✖'), 'run() auto-fails on exception');

echo "-- flexibility: custom Segment instances via columns()\n";
$stars = new class implements \KonradMichalik\PhpProgress\Render\Segment {
    public function key(): string { return 'stars'; }
    public function priority(): int { return 70; }
    public function canDegrade(int $level): bool { return false; }
    public function render(\KonradMichalik\PhpProgress\Task $t, \KonradMichalik\PhpProgress\Frame $f, int $level): ?\KonradMichalik\PhpProgress\Render\Chunk
    {
        return new \KonradMichalik\PhpProgress\Render\Chunk('*' . str_repeat('*', (int) (($t->fraction() ?? 0) * 3)));
    }
};
[$stream, $read] = memStream();
$ms = 0.0;
$bar = Live::bar(10, 'Custom')->columns('label', $stars, 'bar', 'percent')
    ->to($stream)->caps($tty(60))->clock(function () use (&$ms) { return $ms; });
$ms += 100;
$bar->advance(9);
$bar->finish();
assert_true(str_contains(implode('', frames($read())), '****'), 'custom segment renders inline');

echo "-- integration: to() accepts Symfony-style output objects\n";
[$stream, $read] = memStream();
$output = new class($stream) {
    public function __construct(private $s) {}
    public function getStream() { return $this->s; }
};
$ms = 0.0;
$bar = Live::bar(5, 'Duck')->to($output)->caps($tty(60))->clock(function () use (&$ms) { return $ms; });
$ms += 100;
$bar->advance();
$bar->finish();
assert_true(frames($read()) !== [], 'output object with getStream() works');

echo "-- integration: SIGINT restores cursor and re-raises (exit 130)\n";
if (!\function_exists('pcntl_signal') || stripos(PHP_OS, 'WIN') === 0) {
    echo "  skip  pcntl unavailable or Windows\n";
} else {
    $out = tempnam(sys_get_temp_dir(), 'phpprogress_sig_out_');
    $ready = tempnam(sys_get_temp_dir(), 'phpprogress_sig_rdy_');
    @unlink($ready);
    $worker = __DIR__ . '/signal_worker.php';
    // Array form runs php directly (no /bin/sh wrapper), so the pid is the PHP
    // process and a plain SIGINT reaches php-progress's handler.
    $proc = proc_open(
        ['php', $worker, $out, $ready],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    assert_true(\is_resource($proc), 'signal worker started');
    $pid = proc_get_status($proc)['pid'];

    for ($i = 0; $i < 200 && !is_file($ready); $i++) {
        usleep(10000);
    }
    usleep(100000);

    if (\function_exists('posix_kill')) {
        @posix_kill($pid, SIGINT);
    } else {
        exec('kill -INT ' . (int) $pid . ' 2>/dev/null');
    }

    $deadline = microtime(true) + 3.0;
    do {
        $st = proc_get_status($proc);
        usleep(20000);
    } while ($st['running'] && microtime(true) < $deadline);

    // Safety net: never let proc_close() block the suite if the signal missed.
    if ($st['running'] && \function_exists('posix_kill')) {
        @posix_kill($pid, SIGKILL);
        usleep(50000);
    }
    // A process killed by a re-raised signal reports signaled=true / termsig=SIGINT
    // (exitcode is -1 in that case; the shell's 128+signo is a shell convention).
    $signaled = $st['signaled'];
    $termsig = $st['termsig'];
    @fclose($pipes[1]);
    @fclose($pipes[2]);
    @proc_close($proc);

    $raw = (string) @file_get_contents($out);
    @unlink($out);
    @unlink($ready);

    assert_true($signaled === true && $termsig === SIGINT, "worker terminated by re-raised SIGINT (signaled={$signaled}, termsig={$termsig})");
    assert_true(str_contains($raw, "\e[?25h"), 'cursor restored after SIGINT');
    assert_true(str_contains($raw, "\e]9;4;0;0\x07"), 'taskbar progress cleared after SIGINT');
    assert_true(str_contains(substr($raw, -12), "\e[?25h"), 'restore sequence is at the very end');
}

echo "\nALL EXTENDED TESTS PASSED\n";
