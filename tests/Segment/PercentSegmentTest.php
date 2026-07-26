<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\PercentSegment;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class PercentSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new PercentSegment();
        self::assertSame('percent', $seg->key());
        self::assertSame(95, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
    }

    public function testNullWithoutTotal(): void
    {
        self::assertNull((new PercentSegment())->render(new Task(null), $this->frame(), 0));
    }

    public function testFixedFourCharWidth(): void
    {
        $task = new Task(100.0);
        $task->setProgress(67.0, 0.0);
        $chunk = (new PercentSegment())->render($task, $this->frame(colors: 'none'), 0);
        self::assertNotNull($chunk);
        self::assertSame(' 67%', $chunk->plain);
        self::assertNull($chunk->styled);
    }

    public function testStyledWhenColored(): void
    {
        $task = new Task(100.0);
        $task->setProgress(5.0, 0.0);
        $chunk = (new PercentSegment())->render($task, $this->frame(colors: 'truecolor'), 0);
        self::assertNotNull($chunk);
        self::assertSame('  5%', $chunk->plain);
        self::assertNotNull($chunk->styled);
    }
}
