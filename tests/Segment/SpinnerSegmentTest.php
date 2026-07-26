<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Segment\SpinnerSegment;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class SpinnerSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new SpinnerSegment(['a'], 100);
        self::assertSame('spinner', $seg->key());
        self::assertSame(96, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
    }

    public function testFinishedIcons(): void
    {
        $cases = [
            [Status::Success, '✔'],
            [Status::Failure, '✖'],
            [Status::Warning, '⚠'],
            [Status::Stopped, '■'],
        ];
        foreach ($cases as [$status, $icon]) {
            $task = new Task(null);
            $task->status = $status;
            $chunk = (new SpinnerSegment(['a'], 100))->render($task, $this->frame(colors: 'truecolor'), 0);
            self::assertSame($icon, $chunk->plain, "icon for {$status->value}");
            self::assertNotNull($chunk->styled);
        }
    }

    public function testFinishedIconPlainWhenNotColored(): void
    {
        $task = new Task(null);
        $task->status = Status::Success;
        $chunk = (new SpinnerSegment(['a'], 100))->render($task, $this->frame(colors: 'none'), 0);
        self::assertSame('✔', $chunk->plain);
        self::assertNull($chunk->styled);
    }

    public function testProceduralMode(): void
    {
        $fn = static fn (float $phase, Frame $f): string => "=={$phase}==";
        $chunk = (new SpinnerSegment([], 100, null, $fn, 500))->render($this->runningTask(), $this->frame(colors: 'truecolor', nowMs: 250.0), 0);
        self::assertStringContainsString('==0.5==', $chunk->plain);
    }

    public function testProceduralZeroPeriodUsesPhaseZero(): void
    {
        $fn = static fn (float $phase, Frame $f): string => "p{$phase}";
        $chunk = (new SpinnerSegment([], 100, null, $fn, 0))->render($this->runningTask(), $this->frame(nowMs: 999.0), 0);
        self::assertSame('p0', $chunk->plain);
    }

    public function testFrameModeCyclesGlyphs(): void
    {
        $seg = new SpinnerSegment(['a', 'b', 'c'], 100);
        self::assertSame('a', $seg->render($this->runningTask(), $this->frame(nowMs: 0.0), 0)->plain);
        self::assertSame('b', $seg->render($this->runningTask(), $this->frame(nowMs: 100.0), 0)->plain);
        self::assertSame('c', $seg->render($this->runningTask(), $this->frame(nowMs: 200.0), 0)->plain);
        self::assertSame('a', $seg->render($this->runningTask(), $this->frame(nowMs: 300.0), 0)->plain);
    }

    public function testFrameModeColoredAndPlain(): void
    {
        $seg = new SpinnerSegment(['x'], 100);
        self::assertNotNull($seg->render($this->runningTask(), $this->frame(colors: 'truecolor'), 0)->styled);
        self::assertNull($seg->render($this->runningTask(), $this->frame(colors: 'none'), 0)->styled);
    }

    public function testCustomColorOverride(): void
    {
        $chunk = (new SpinnerSegment(['x'], 100, '#ff0000'))->render($this->runningTask(), $this->frame(colors: 'truecolor'), 0);
        self::assertStringContainsString("\e[38;2;255;0;0m", (string) $chunk->styled);
    }

    public function testEmptyFramesDegradeToEmptySlot(): void
    {
        $colored = (new SpinnerSegment([], 100))->render($this->runningTask(), $this->frame(colors: 'truecolor'), 0);
        self::assertSame('', $colored->plain);
        self::assertSame('', $colored->styled);

        $plain = (new SpinnerSegment([], 100))->render($this->runningTask(), $this->frame(colors: 'none'), 0);
        self::assertSame('', $plain->plain);
        self::assertNull($plain->styled);
    }

    public function testNonPositiveIntervalDegradesToEmptySlot(): void
    {
        $chunk = (new SpinnerSegment(['a'], 0))->render($this->runningTask(), $this->frame(), 0);
        self::assertSame('', $chunk->plain);
    }

    private function runningTask(): Task
    {
        $task = new Task(null);
        $task->status = Status::Running;

        return $task;
    }
}
