<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Support\Text;

final class LiveBarTest extends TestCase
{
    public function testAutoStartsOnFirstMutation(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'Lazy', 60, $clock, $stream);
        $clock->ms += 100;
        $bar->advance();
        self::assertNotSame([], $this->frames($read()));
        $bar->finish();
    }

    public function testWidthDisciplineAcrossSizes(): void
    {
        foreach ([100, 64, 40, 30, 24] as $width) {
            [$stream, $read] = $this->memStream();
            $clock = new Clock();
            $bar = $this->bar(100.0, 'Content Migration Project', $width, $clock, $stream)->start();
            for ($i = 0; $i < 100; $i++) {
                $clock->ms += 120;
                $bar->advance();
                if ($i === 30) {
                    $bar->set('table', 'tx_sitepackage_domain_model_item');
                }
                if ($i === 60) {
                    $bar->clear('table');
                }
            }
            $bar->finish();
            $max = 0;
            foreach ($this->frames($read()) as $frame) {
                $max = max($max, Text::width($frame));
            }
            self::assertLessThanOrEqual($width, $max, "width {$width} overflow");
        }
    }

    public function testFieldsAppearAndDisappear(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'Sync', 100, $clock, $stream)->start();
        $clock->ms += 200;
        $bar->advance();
        $clock->ms += 200;
        $bar->set('file', 'assets/video/intro.mp4');
        $clock->ms += 200;
        $bar->advance();
        $clock->ms += 200;
        $bar->clear('file');
        $clock->ms += 200;
        $bar->advance();
        $bar->finish();

        $frames = $this->frames($read());
        self::assertNotSame([], array_filter($frames, static fn ($f) => str_contains($f, 'intro.mp4')));
        self::assertStringNotContainsString('intro.mp4', (string) end($frames));
    }

    public function testNarrowReflowDropsEtaRateCountBeforePercent(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(1000.0, 'A very long migration label that will not fit', 34, $clock, $stream)->start();
        for ($i = 0; $i < 500; $i++) {
            $clock->ms += 50;
            $bar->advance();
        }
        $frames = $this->frames($read());
        $last = (string) end($frames);
        self::assertStringContainsString('%', $last);
        self::assertStringNotContainsString('eta', $last);
        self::assertStringNotContainsString('it/s', $last);
        self::assertStringNotContainsString('/1000', $last);
    }

    public function testPercentHasFixedWidth(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(100.0, 'x')->columns('bar', 'percent')
            ->to($stream)->caps($this->tty(60))->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 100; $i++) {
            $clock->ms += 100;
            $bar->advance();
        }
        $bar->finish();
        $widths = array_unique(array_map(
            static fn ($f) => Text::width(rtrim($f)),
            $this->frames($read()),
        ));
        self::assertCount(1, $widths, 'line width constant 1%..100%');
    }

    public function testIndeterminateBarPulsesWithoutPercent(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(null, 'Streaming', 60, $clock, $stream)->start();
        for ($i = 0; $i < 10; $i++) {
            $clock->ms += 100;
            $bar->tick();
        }
        $bar->finish();
        $frames = $this->frames($read());
        self::assertStringNotContainsString('%', implode('', $frames));
        self::assertGreaterThan(3, count(array_unique(array_slice($frames, 0, 10))));
    }

    public function testOsc94EmittedAndCleared(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'x', 60, $clock, $stream)->start();
        $clock->ms += 100;
        $bar->advance(5);
        $bar->finish();
        $raw = $read();
        self::assertStringContainsString("\e]9;4;1;", $raw);
        self::assertStringContainsString("\e]9;4;0;0\x07", $raw);
    }

    public function testTransientClearsLineOnFinish(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'Temp', 60, $clock, $stream)->transient()->start();
        $clock->ms += 100;
        $bar->advance();
        $bar->finish();
        $frames = $this->frames($read());
        self::assertSame('', (string) end($frames));
    }

    public function testProgressAndTotalSetters(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(null, 'x', 60, $clock, $stream)->start();
        $bar->total(50.0);
        $clock->ms += 100;
        $bar->progress(25.0);
        self::assertSame(0.5, $bar->task()->fraction());
    }

    public function testTextSwapsLabel(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'Old', 60, $clock, $stream)->start();
        $clock->ms += 100;
        $bar->text('New label');
        self::assertSame('New label', $bar->task()->label);
    }

    public function testUnitBytesRendersHumanReadable(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(1024.0 * 1024, 'DL')->columns('count')->unit('bytes')
            ->to($stream)->caps($this->tty(60, 'none'))->clock($clock)->handleSignals(false)->start();
        $clock->ms += 100;
        $bar->progress(512.0 * 1024);
        $frames = $this->frames($read());
        self::assertStringContainsString('512.0 KB/1.0 MB', (string) end($frames));
    }

    public function testFinishFillsBarAndPersistsFinalText(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'Job', 60, $clock, $stream)->start();
        $clock->ms += 100;
        $bar->advance();
        $bar->finish('All done'); // bars have no spinner column, so no icon -- just fill + persist
        $frames = $this->frames($read());
        $last = (string) end($frames);
        self::assertStringContainsString('100%', $last);
        self::assertStringContainsString('All done', $last);
        self::assertTrue($bar->task()->status->finished());
    }

    public function testFailStopPersistAndMarkFinished(): void
    {
        foreach (['fail', 'warn', 'stop'] as $method) {
            [$stream, $read] = $this->memStream();
            $clock = new Clock();
            $bar = $this->bar(10.0, 'x', 60, $clock, $stream)->start();
            $clock->ms += 100;
            $bar->advance();
            $bar->{$method}();
            self::assertTrue($bar->task()->status->finished(), "{$method} finishes the task");
            self::assertNotSame([], $this->frames($read()));
        }
    }

    public function testEndIsIdempotentAfterFinish(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'x', 60, $clock, $stream)->start();
        $clock->ms += 100;
        $bar->finish();
        $before = $read();
        $bar->fail(); // already finished -> no-op
        self::assertSame($before, $read());
    }

    public function testPrintlnInterleavesLogLine(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'Work', 60, $clock, $stream)->start();
        $clock->ms += 100;
        $bar->advance();
        $bar->println('warning: skipped record 42');
        $bar->finish();
        self::assertStringContainsString("skipped record 42\n", $read());
    }

    public function testEmptyCustomFramesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Live::spinner('x')->frames([]);
    }

    public function testDoubleStartIsIdempotent(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = $this->bar(10.0, 'x', 60, $clock, $stream);
        self::assertSame($bar, $bar->start());
        self::assertSame($bar, $bar->start()); // second call returns early
        $bar->finish();
    }

    public function testThemeAndBarWidthSetters(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(100.0, 'x')->columns('bar', 'percent')
            ->theme(new Theme())->barWidth(30)
            ->to($stream)->caps($this->tty(100, 'none'))->clock($clock)->handleSignals(false)->start();
        $clock->ms += 100;
        $bar->progress(50.0);
        $bar->finish();
        // barWidth(30) caps the bar to 30 columns even though the terminal is 100 wide.
        $frames = $this->frames($read());
        preg_match('/[\x{2500}\x{2588}-\x{258F}]+/u', (string) end($frames), $m);
        self::assertSame(30, Text::width($m[0] ?? ''));
    }

    public function testExpandFillsTerminal(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(100.0, '')->columns('bar')->expand()
            ->to($stream)->caps($this->tty(50, 'none'))->clock($clock)->handleSignals(false)->start();
        $clock->ms += 100;
        $bar->progress(50.0);
        $bar->finish();
        $max = 0;
        foreach ($this->frames($read()) as $frame) {
            $max = max($max, Text::width($frame));
        }
        self::assertSame(50, $max);
    }

    private function bar(?float $total, string $label, int $width, Clock $clock, $stream, string $colors = 'truecolor'): Live
    {
        return Live::bar($total, $label)
            ->to($stream)->caps($this->tty($width, $colors))->clock($clock)->handleSignals(false);
    }
}
