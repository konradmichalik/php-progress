<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\CountSegment;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class CountSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new CountSegment();
        self::assertSame('count', $seg->key());
        self::assertSame(50, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
    }

    public function testNullWithoutTotal(): void
    {
        self::assertNull((new CountSegment())->render(new Task(null), $this->frame(), 0));
    }

    public function testItemsCountRightAligned(): void
    {
        $task = new Task(100.0);
        $task->setProgress(7.0, 0.0);
        $chunk = (new CountSegment())->render($task, $this->frame(colors: 'none'), 0);
        self::assertNotNull($chunk);
        self::assertSame('  7/100', $chunk->plain);
    }

    public function testBytesUnit(): void
    {
        $task = new Task(1024.0 * 1024, 'x', 'bytes');
        $task->setProgress(512.0 * 1024, 0.0);
        $chunk = (new CountSegment())->render($task, $this->frame(colors: 'truecolor'), 0);
        self::assertNotNull($chunk);
        self::assertSame('512.0 KB/1.0 MB', $chunk->plain);
        self::assertNotNull($chunk->styled);
    }
}
