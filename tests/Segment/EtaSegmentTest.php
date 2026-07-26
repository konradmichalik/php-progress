<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\EtaSegment;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class EtaSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new EtaSegment();
        self::assertSame('eta', $seg->key());
        self::assertSame(45, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
    }

    public function testNullWhenFinished(): void
    {
        $task = new Task(100.0);
        $task->status = Status::Success;
        self::assertNull((new EtaSegment())->render($task, $this->frame(), 0));
    }

    public function testNullWithoutRate(): void
    {
        self::assertNull((new EtaSegment())->render(new Task(100.0), $this->frame(), 0));
    }

    public function testRendersEta(): void
    {
        $task = new Task(100.0);
        $task->setProgress(0.0, 0.0);
        $task->setProgress(50.0, 1000.0); // 50/s, 50 remaining => 1s
        $chunk = (new EtaSegment())->render($task, $this->frame(colors: 'truecolor'), 0);
        self::assertNotNull($chunk);
        self::assertSame('eta 0:01', $chunk->plain);
        self::assertNotNull($chunk->styled);
    }
}
