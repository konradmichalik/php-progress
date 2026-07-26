<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Task;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testFractionNullWithoutTotal(): void
    {
        self::assertNull((new Task(null))->fraction());
    }

    public function testFractionNullWhenTotalNonPositive(): void
    {
        self::assertNull((new Task(0.0))->fraction());
        self::assertNull((new Task(-5.0))->fraction());
    }

    public function testFractionIsClamped(): void
    {
        $task = new Task(100.0);
        $task->setProgress(50.0, 0.0);
        self::assertSame(0.5, $task->fraction());
    }

    public function testSetProgressClampsToTotal(): void
    {
        $task = new Task(10.0);
        $task->setProgress(999.0, 0.0);
        self::assertSame(10.0, $task->completed);
        self::assertSame(1.0, $task->fraction());
    }

    public function testAdvanceAccumulates(): void
    {
        $task = new Task(null);
        $task->advance(3.0, 0.0);
        $task->advance(2.0, 10.0);
        self::assertSame(5.0, $task->completed);
    }

    public function testAdvanceWithoutTotalIsUnbounded(): void
    {
        $task = new Task(null);
        $task->setProgress(1e6, 0.0);
        self::assertSame(1e6, $task->completed);
    }

    public function testFieldsSetAndClear(): void
    {
        $task = new Task();
        $task->set('host', 'db01');
        self::assertSame(['host' => 'db01'], $task->fields());
        $task->clear('host');
        self::assertSame([], $task->fields());
    }

    public function testStickyFields(): void
    {
        $task = new Task();
        $task->set('host', 'db01', sticky: true);
        $task->set('debug', 'x');
        self::assertTrue($task->hasStickyFields());
        self::assertSame(['host' => 'db01'], $task->fields(onlySticky: true));
        self::assertSame(['host' => 'db01', 'debug' => 'x'], $task->fields());
    }

    public function testClearingStickyRemovesStickiness(): void
    {
        $task = new Task();
        $task->set('host', 'db01', sticky: true);
        $task->clear('host');
        self::assertFalse($task->hasStickyFields());
    }

    public function testElapsedZeroBeforeStart(): void
    {
        self::assertSame(0.0, (new Task())->elapsedSeconds(5000.0));
    }

    public function testElapsedComputedAfterStart(): void
    {
        $task = new Task();
        $task->startedAtMs = 1000.0;
        self::assertSame(4.0, $task->elapsedSeconds(5000.0));
    }

    public function testElapsedNeverNegative(): void
    {
        $task = new Task();
        $task->startedAtMs = 5000.0;
        self::assertSame(0.0, $task->elapsedSeconds(1000.0));
    }
}
