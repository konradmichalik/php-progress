<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\RateSegment;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class RateSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new RateSegment();
        self::assertSame('rate', $seg->key());
        self::assertSame(40, $seg->priority());
        self::assertFalse($seg->canDegrade(0));
    }

    public function testNullWithoutRate(): void
    {
        self::assertNull((new RateSegment())->render(new Task(100.0), $this->frame(), 0));
    }

    public function testSlowRateOneDecimal(): void
    {
        $task = new Task(1000.0);
        $task->setProgress(0.0, 0.0);
        $task->setProgress(50.0, 1000.0); // 50/s
        $chunk = (new RateSegment())->render($task, $this->frame(colors: 'none'), 0);
        self::assertNotNull($chunk);
        self::assertSame('50.0 it/s', $chunk->plain);
    }

    public function testFastRateInteger(): void
    {
        $task = new Task(1000.0);
        $task->setProgress(0.0, 0.0);
        $task->setProgress(200.0, 1000.0); // 200/s
        $chunk = (new RateSegment())->render($task, $this->frame(colors: 'truecolor'), 0);
        self::assertNotNull($chunk);
        self::assertSame('200 it/s', $chunk->plain);
        self::assertNotNull($chunk->styled);
    }

    public function testBytesRate(): void
    {
        $task = new Task(1e9, 'x', 'bytes');
        $task->setProgress(0.0, 0.0);
        $task->setProgress(1024.0 * 1024, 1000.0); // 1 MB/s
        $chunk = (new RateSegment())->render($task, $this->frame(colors: 'none'), 0);
        self::assertNotNull($chunk);
        self::assertSame('1.0 MB/s', $chunk->plain);
    }
}
