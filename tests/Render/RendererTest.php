<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Render;

use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Render\Layout;
use KonradMichalik\PhpProgress\Render\Renderer;
use KonradMichalik\PhpProgress\Segment\BarSegment;
use KonradMichalik\PhpProgress\Segment\LabelSegment;
use KonradMichalik\PhpProgress\Segment\PercentSegment;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;
use KonradMichalik\PhpProgress\Terminal\Capabilities;
use KonradMichalik\PhpProgress\Tests\Clock;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class RendererTest extends TestCase
{
    public function testDrawIsNoopBeforeStart(): void
    {
        [$stream, $read] = $this->memStream();
        $renderer = new Renderer($stream, $this->tty(60), new Theme(), 12.0, new Clock());
        $renderer->draw(new Task(100.0), $this->layout(), force: true);
        self::assertSame('', $read());
    }

    public function testFpsThrottleSkipsRapidRedraws(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $renderer = new Renderer($stream, $this->tty(60), new Theme(), 12.0, $clock);
        $renderer->start();
        $task = new Task(100.0, 'x');
        $renderer->draw($task, $this->layout(), force: true);
        $before = strlen($read());
        // Same timestamp, not forced -> below 1000/12 ms -> skipped.
        $renderer->draw($task, $this->layout());
        self::assertSame($before, strlen($read()));
    }

    public function testRefreshWidthFollowsResize(): void
    {
        $saved = getenv('COLUMNS');
        putenv('COLUMNS=100');
        try {
            [$stream, $read] = $this->memStream();
            $clock = new Clock();
            // refreshWidth=true simulates auto-detected caps (Live sets this when no caps injected).
            $renderer = new Renderer($stream, $this->tty(80), new Theme(), 12.0, $clock, true, false);
            $renderer->start();
            $task = new Task(100.0, '');
            $task->setProgress(50.0, 0.0);
            $renderer->draw($task, $this->layout(), force: true);
            putenv('COLUMNS=30');
            $clock->ms += 2100; // > 2s -> width re-read
            $renderer->draw($task, $this->layout(), force: true);
            $frames = $this->frames($read());
            self::assertLessThanOrEqual(30, Text::width((string) end($frames)));
        } finally {
            $saved === false ? putenv('COLUMNS') : putenv("COLUMNS={$saved}");
        }
    }

    public function testIndeterminateEmitsOsc3(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        Live::bar(null, 'x')->to($stream)->caps($this->tty(60))->clock($clock)->handleSignals(false)
            ->start()->tick();
        self::assertStringContainsString("\e]9;4;3;", $read());
    }

    public function testEraseTailWhenLineShrinks(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(10.0, 'x')->to($stream)->caps($this->tty(80))->clock($clock)->handleSignals(false)->start();
        $clock->ms += 100;
        $bar->set('field', 'a-fairly-long-value-here');
        $clock->ms += 100;
        $bar->clear('field'); // shorter line now -> erase-to-end appended
        self::assertStringContainsString(Ansi::ERASE_TO_END, $read());
        $bar->finish();
    }

    public function testNonTtyEmitsPlainThrottledLines(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(100.0, 'CI import')->to($stream)->caps(new Capabilities(false, 80, 'none', true))
            ->clock($clock)->start();
        for ($i = 0; $i < 100; $i++) {
            $clock->ms += 50;
            $bar->advance();
        }
        $bar->finish();
        $raw = $read();
        self::assertStringNotContainsString("\e[", $raw);
        $lines = array_filter(explode("\n", $raw));
        self::assertGreaterThanOrEqual(5, count($lines));
        self::assertLessThanOrEqual(20, count($lines));
        self::assertStringContainsString('[ok] CI import 100% (100/100)', $raw);
    }

    public function testNonTtyLogsFieldsAndElapsed(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock(1000.0); // start clock > 0 so startedAtMs > 0 (enables elapsed)
        $bar = Live::bar(10.0, 'Job')->to($stream)->caps(new Capabilities(false, 80, 'none', true))
            ->clock($clock)->start();
        $bar->set('host', 'db01');
        $clock->ms += 3000; // > 2s -> a line is due
        $bar->advance();
        $bar->finish();
        $raw = $read();
        self::assertStringContainsString('host=db01', $raw);
        self::assertStringContainsString('elapsed', $raw);
    }

    public function testNonTtyFinishedStates(): void
    {
        foreach (['fail' => '[fail]', 'warn' => '[warn]', 'stop' => '[stopped]'] as $method => $tag) {
            [$stream, $read] = $this->memStream();
            $clock = new Clock();
            $bar = Live::bar(10.0, 'x')->to($stream)->caps(new Capabilities(false, 80, 'none', true))
                ->clock($clock)->start();
            $bar->{$method}();
            self::assertStringContainsString($tag, $read(), "tag for {$method}");
        }
    }

    public function testNonTtyPrintlnWritesPlainLine(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(10.0, 'x')->to($stream)->caps(new Capabilities(false, 80, 'none', true))
            ->clock($clock)->start();
        $bar->println('hello log');
        self::assertStringContainsString("hello log\n", $read());
    }

    public function testRestoreTerminalWritesResetAndClearsStreams(): void
    {
        [$stream, $read] = $this->memStream();
        $renderer = new Renderer($stream, $this->tty(60), new Theme(), 12.0, new Clock(), false, false);
        $renderer->start(); // registers the stream in the cursor-restore set

        $method = new \ReflectionMethod(Renderer::class, 'restoreTerminal');
        $method->invoke(null);

        $raw = $read();
        self::assertStringContainsString(Ansi::osc94(0), $raw);
        self::assertStringContainsString(Ansi::SHOW_CURSOR, $raw);

        // A second restore writes nothing more (stream set was cleared).
        $before = strlen($read());
        $method->invoke(null);
        self::assertSame($before, strlen($read()));
    }

    public function testFinishIsNoopWhenInactive(): void
    {
        [$stream, $read] = $this->memStream();
        $renderer = new Renderer($stream, $this->tty(60), new Theme(), 12.0, new Clock());
        // never started -> active is false -> finish returns immediately
        $renderer->finish(new Task(100.0), $this->layout(), false);
        self::assertSame('', $read());
    }

    public function testInstallSignalHandlersRegistersAndIsIdempotent(): void
    {
        if (!\function_exists('pcntl_signal') || !\function_exists('pcntl_async_signals')) {
            self::markTestSkipped('pcntl unavailable');
        }
        $prevInt = pcntl_signal_get_handler(SIGINT);
        $prevTerm = pcntl_signal_get_handler(SIGTERM);
        $installed = new \ReflectionProperty(Renderer::class, 'signalsInstalled');
        $installed->setValue(null, false); // force the first-call registration path

        try {
            [$s1] = $this->memStream();
            (new Renderer($s1, $this->tty(60), new Theme(), 12.0, new Clock(), false, true))->start();
            self::assertInstanceOf(\Closure::class, pcntl_signal_get_handler(SIGINT));

            // A second start hits the "already installed" guard and returns early.
            [$s2] = $this->memStream();
            (new Renderer($s2, $this->tty(60), new Theme(), 12.0, new Clock(), false, true))->start();
            self::assertInstanceOf(\Closure::class, pcntl_signal_get_handler(SIGTERM));
        } finally {
            pcntl_signal(SIGINT, \is_callable($prevInt) ? $prevInt : SIG_DFL);
            pcntl_signal(SIGTERM, \is_callable($prevTerm) ? $prevTerm : SIG_DFL);
            pcntl_async_signals(false);
            $installed->setValue(null, false);
        }
    }

    private function layout(): Layout
    {
        return new Layout([new LabelSegment(), new BarSegment(), new PercentSegment()]);
    }
}
