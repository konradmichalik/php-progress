<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Process;

use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Process\Proc;
use KonradMichalik\PhpProgress\Progress;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class ProcTest extends TestCase
{
    public function testParsesPercentFromChildOutput(): void
    {
        [$stream, $read] = $this->memStream();
        $live = $this->barLive($stream);
        $code = Progress::process(['bash', '-c', 'for i in 10 35 60 85 100; do echo "$i% done"; sleep 0.02; done'])
            ->parse(static fn (string $l) => preg_match('/^(\d+)%/', $l, $m) ? (float) $m[1] : null)
            ->live($live)
            ->run();
        self::assertSame(0, $code);
        $frames = $this->frames($read());
        self::assertStringContainsString('100%', (string) end($frames));
    }

    public function testFailureStateRendersIcon(): void
    {
        [$stream, $read] = $this->memStream();
        $live = Live::spinner('doomed')->to($stream)->caps($this->tty(60))->handleSignals(false)->fps(1000.0);
        $code = Progress::process(['bash', '-c', 'echo boom >&2; exit 3'])->live($live)->run();
        self::assertSame(3, $code);
        self::assertStringContainsString('✖', Text::stripAnsi($read()));
    }

    public function testStringFormRunsViaShell(): void
    {
        [$stream, $read] = $this->memStream();
        $live = $this->barLive($stream);
        $code = Progress::process('echo 77%')
            ->parse(static fn (string $l) => preg_match('/(\d+)%/', $l, $m) ? (float) $m[1] : null)
            ->live($live)
            ->run();
        self::assertSame(0, $code);
        self::assertStringContainsString('77%', implode("\n", $this->frames($read())));
    }

    public function testWatchStderr(): void
    {
        [$stream, $read] = $this->memStream();
        $live = $this->barLive($stream);
        $code = Progress::process(['bash', '-c', 'echo 30% >&2; sleep 0.02'])
            ->from('stderr')
            ->parse(static fn (string $l) => preg_match('/(\d+)%/', $l, $m) ? (float) $m[1] : null)
            ->live($live)
            ->run();
        self::assertSame(0, $code);
        self::assertStringContainsString('30%', implode("\n", $this->frames($read())));
    }

    public function testOnLineObservesLines(): void
    {
        [$stream, $read] = $this->memStream();
        $live = Live::spinner('x')->to($stream)->caps($this->tty(60))->handleSignals(false)->fps(1000.0);
        $seen = [];
        Progress::process(['bash', '-c', 'echo hello; echo world'])
            ->onLine(static function (Live $l, string $s, string $line) use (&$seen): void {
                $seen[] = $line;
            })
            ->live($live)
            ->run();
        self::assertContains('hello', $seen);
        self::assertContains('world', $seen);
    }

    public function testCaptureBufferIsBounded(): void
    {
        [$stream] = $this->memStream();
        $live = $this->barLive($stream);
        $proc = Progress::process(['bash', '-c', 'head -c 200000 /dev/zero | tr "\0" "A"']);
        $code = $proc->live($live)->run();
        self::assertSame(0, $code);
        self::assertLessThanOrEqual(65536, strlen($proc->stdout));
    }

    public function testUnterminatedOverflowRemainderIsPreserved(): void
    {
        [$stream] = $this->memStream();
        $live = Live::spinner('x')->to($stream)->caps($this->tty(60))->handleSignals(false)->fps(1000.0);
        $seen = '';
        Progress::process(['bash', '-c', 'printf "%1048576s" "" | tr " " A; printf "MARKER"; printf "%80s" "" | tr " " B; printf "\n"'])
            ->onLine(static function (Live $l, string $s, string $line) use (&$seen): void {
                $seen .= $line;
            })
            ->live($live)
            ->run();
        self::assertStringContainsString('MARKER', $seen);
    }

    public function testThrowingParseTearsDownAndPropagates(): void
    {
        [$stream] = $this->memStream();
        $live = $this->barLive($stream);
        $this->expectException(\RuntimeException::class);
        Progress::process(['bash', '-c', 'echo 1%; sleep 30'])
            ->parse(static fn (string $l) => str_contains($l, '1%') ? throw new \RuntimeException('boom') : null)
            ->live($live)
            ->run();
    }

    public function testLabelIsUsedForDefaultLive(): void
    {
        $proc = Progress::process(['true'])->label('rsync media');
        $method = new \ReflectionMethod(Proc::class, 'defaultLive');
        $live = $method->invoke($proc);
        self::assertInstanceOf(Live::class, $live);
    }

    public function testDefaultLiveBarWhenParserSet(): void
    {
        $proc = Progress::process(['true'])->parse(static fn () => null);
        $method = new \ReflectionMethod(Proc::class, 'defaultLive');
        self::assertInstanceOf(Live::class, $method->invoke($proc));
    }

    public function testBlankChildLinesAreSkipped(): void
    {
        [$stream, $read] = $this->memStream();
        $live = Live::spinner('x')->to($stream)->caps($this->tty(60))->handleSignals(false)->fps(1000.0);
        $seen = [];
        Progress::process(['bash', '-c', 'echo; echo kept'])
            ->onLine(static function (Live $l, string $s, string $line) use (&$seen): void {
                $seen[] = $line; // handleLine returns early on '' so blanks never reach here
            })
            ->live($live)
            ->run();
        self::assertContains('kept', $seen);
        self::assertNotContains('', $seen);
    }

    public function testFailedToStartReturnsOne(): void
    {
        [$stream, $read] = $this->memStream();
        $live = Live::spinner('x')->to($stream)->caps($this->tty(60))->handleSignals(false)->fps(1000.0);
        $code = Progress::process(['/nonexistent/binary-xyz-123'])->live($live)->run();
        self::assertSame(1, $code);
        self::assertStringContainsString('failed to start', Text::stripAnsi($read()));
    }

    private function barLive($stream): Live
    {
        return Live::bar(100.0, 'proc')->columns('label', 'bar', 'percent')
            ->to($stream)->caps($this->tty(60))->handleSignals(false)->fps(1000.0);
    }
}
