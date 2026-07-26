<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\ElapsedSegment;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class ElapsedSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new ElapsedSegment();
        self::assertSame('elapsed', $seg->key());
        self::assertSame(30, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
    }

    public function testNullBeforeStart(): void
    {
        self::assertNull((new ElapsedSegment())->render(new Task(null), $this->frame(), 0));
    }

    public function testRendersDuration(): void
    {
        $task = new Task(null);
        $task->startedAtMs = 1000.0;
        $chunk = (new ElapsedSegment())->render($task, $this->frame(colors: 'none', nowMs: 5000.0), 0);
        self::assertNotNull($chunk);
        self::assertSame('(0:04)', $chunk->plain);
        self::assertNull($chunk->styled);
    }

    public function testStyledWhenColored(): void
    {
        $task = new Task(null);
        $task->startedAtMs = 0.0;
        $task->startedAtMs = 1.0; // > 0 so it renders
        $chunk = (new ElapsedSegment())->render($task, $this->frame(colors: 'truecolor', nowMs: 1000.0), 0);
        self::assertNotNull($chunk);
        self::assertNotNull($chunk->styled);
    }
}
