<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Process\Proc;
use KonradMichalik\PhpProgress\Progress;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;

final class IntegrationTest extends TestCase
{
    public function testFacadeDelegates(): void
    {
        self::assertInstanceOf(Live::class, Progress::bar(100.0, 'x'));
        self::assertInstanceOf(Live::class, Progress::spinner('x'));
        self::assertInstanceOf(Proc::class, Progress::process(['true']));
    }

    public function testTrackYieldsAllItemsAndCountsTotal(): void
    {
        [$stream, $read] = $this->memStream();
        $live = Live::bar(null, 'Sum')->to($stream)->caps($this->tty(60))->handleSignals(false);
        $sum = 0;
        foreach (Progress::track(range(1, 5), 'Sum', null, $live) as $n) {
            $sum += $n;
        }
        self::assertSame(15, $sum);
        self::assertSame(5.0, $live->task()->total); // total inferred from countable
        self::assertTrue($live->task()->status->finished());
    }

    public function testTrackWithExplicitTotal(): void
    {
        [$stream] = $this->memStream();
        $live = Live::bar(null, 'x')->to($stream)->caps($this->tty(60))->handleSignals(false);
        foreach (Progress::track(range(1, 3), 'x', 3, $live) as $n) {
            // no-op
        }
        self::assertSame(3.0, $live->task()->total);
    }

    public function testTrackWithNonCountableLeavesTotalNull(): void
    {
        [$stream] = $this->memStream();
        $live = Live::bar(null, 'gen')->to($stream)->caps($this->tty(60))->handleSignals(false);
        $gen = (static function () {
            yield 1;
            yield 2;
        })();
        $seen = [];
        foreach (Progress::track($gen, 'gen', null, $live) as $n) {
            $seen[] = $n;
        }
        self::assertSame([1, 2], $seen);
        self::assertNull($live->task()->total);
    }

    public function testTrackBreakTearsDownCleanly(): void
    {
        [$stream, $read] = $this->memStream();
        $live = Live::bar(null, 'Break')->to($stream)->caps($this->tty(60))->handleSignals(false);
        foreach (Progress::track(range(1, 10), 'Break', 10, $live) as $n) {
            if ($n === 3) {
                break;
            }
        }
        unset($live); // drop the last reference so the generator's finally runs
        gc_collect_cycles();
        self::assertStringContainsString("\e[?25h", $read());
    }

    public function testTrackRethrowsAndFailsOnException(): void
    {
        [$stream] = $this->memStream();
        $live = Live::bar(null, 'E')->to($stream)->caps($this->tty(60))->handleSignals(false);
        // The exception must originate *inside* the iteration (not the consumer
        // loop body) so it propagates through track()'s try/catch -> fail().
        $items = (static function () {
            yield 1;
            throw new \RuntimeException('boom');
        })();
        try {
            foreach (Progress::track($items, 'E', null, $live) as $n) {
                // consume
            }
            self::fail('expected exception');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }
        self::assertTrue($live->task()->status->finished());
        self::assertSame(\KonradMichalik\PhpProgress\Status::Failure, $live->task()->status);
    }

    public function testRunReturnsResultAndAutoSucceeds(): void
    {
        [$stream, $read] = $this->memStream();
        $result = Live::spinner('Wrapped')->to($stream)->caps($this->tty(60))->handleSignals(false)
            ->run(static fn () => 42);
        self::assertSame(42, $result);
        self::assertStringContainsString('✔', Text::stripAnsi($read()));
    }

    public function testRunRethrowsAndAutoFails(): void
    {
        [$stream, $read] = $this->memStream();
        try {
            Live::spinner('Doomed')->to($stream)->caps($this->tty(60))->handleSignals(false)
                ->run(static fn () => throw new \RuntimeException('x'));
            self::fail('expected exception');
        } catch (\RuntimeException) {
        }
        self::assertStringContainsString('✖', Text::stripAnsi($read()));
    }

    public function testRunDoesNotDoubleFinish(): void
    {
        [$stream, $read] = $this->memStream();
        $result = Live::spinner('Self')->to($stream)->caps($this->tty(60))->handleSignals(false)
            ->run(static function (Live $live) {
                $live->succeed('done early');

                return 7;
            });
        self::assertSame(7, $result);
        self::assertStringContainsString('done early', Text::stripAnsi($read()));
    }

    public function testDestructorRestoresCursor(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $live = Live::bar(10.0, 'Leak')->to($stream)->caps($this->tty(60))->clock($clock)->handleSignals(false);
        $clock->ms += 100;
        $live->advance();
        unset($live);
        $raw = $read();
        self::assertStringContainsString("\e[?25h", $raw);
        self::assertStringContainsString("\e]9;4;0;0\x07", $raw);
    }

    public function testCustomSegmentViaColumns(): void
    {
        $stars = new class implements Segment {
            public function key(): string
            {
                return 'stars';
            }

            public function priority(): int
            {
                return 70;
            }

            public function canDegrade(int $level): bool
            {
                return false;
            }

            public function render(Task $task, Frame $frame, int $level): ?Chunk
            {
                return new Chunk('*' . str_repeat('*', (int) (($task->fraction() ?? 0) * 3)));
            }
        };

        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $bar = Live::bar(10.0, 'Custom')->columns('label', $stars, 'bar', 'percent')
            ->to($stream)->caps($this->tty(60))->clock($clock)->handleSignals(false);
        $clock->ms += 100;
        $bar->advance(9);
        $bar->finish();
        self::assertStringContainsString('****', implode('', $this->frames($read())));
    }

    public function testToAcceptsSymfonyStyleOutputObject(): void
    {
        [$stream, $read] = $this->memStream();
        $output = new class($stream) {
            /** @param resource $s */
            public function __construct(private $s)
            {
            }

            /** @return resource */
            public function getStream()
            {
                return $this->s;
            }
        };
        $clock = new Clock();
        $bar = Live::bar(5.0, 'Duck')->to($output)->caps($this->tty(60))->clock($clock)->handleSignals(false);
        $clock->ms += 100;
        $bar->advance();
        $bar->finish();
        self::assertNotSame([], $this->frames($read()));
    }

    public function testToRejectsInvalidTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Live::bar(10.0, 'x')->to('not a stream');
    }
}
