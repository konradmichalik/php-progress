<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\BarSegment;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class BarSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new BarSegment();
        self::assertSame('bar', $seg->key());
        self::assertSame(100, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
        self::assertSame(6, $seg->minWidth());
    }

    public function testRenderUsesMinWidth(): void
    {
        // The Segment (non-flex) render() draws at minWidth.
        $task = new Task(100.0);
        $task->setProgress(50.0, 0.0);
        $chunk = (new BarSegment())->render($task, $this->frame(colors: 'none'), 0);
        self::assertSame(6, Text::width($chunk->plain));
    }

    public function testDeterminateFillColored(): void
    {
        $task = new Task(100.0);
        $task->status = Status::Running;
        $task->setProgress(50.0, 0.0);
        $chunk = (new BarSegment())->renderFlex($task, $this->frame(colors: 'truecolor', nowMs: 500.0), 20);
        self::assertSame(20, Text::width($chunk->plain));
        self::assertNotNull($chunk->styled);
    }

    public function testDeterminateFill256AndBasicDepths(): void
    {
        $task = new Task(100.0);
        $task->status = Status::Running;
        $task->setProgress(30.0, 0.0);
        foreach (['256', 'basic'] as $depth) {
            $chunk = (new BarSegment())->renderFlex($task, $this->frame(colors: $depth), 16);
            self::assertSame(16, Text::width($chunk->plain));
            self::assertNotNull($chunk->styled);
        }
    }

    public function testIndeterminatePulse(): void
    {
        $task = new Task(null);
        $task->status = Status::Running;
        $chunk = (new BarSegment())->renderFlex($task, $this->frame(colors: 'truecolor', nowMs: 300.0), 20);
        self::assertSame(20, Text::width($chunk->plain));
    }

    public function testIndeterminatePulseMonochrome(): void
    {
        $task = new Task(null);
        $task->status = Status::Running;
        $chunk = (new BarSegment())->renderFlex($task, $this->frame(colors: 'none', nowMs: 300.0), 20);
        self::assertSame(20, Text::width($chunk->plain));
        self::assertNull($chunk->styled);
    }
}
