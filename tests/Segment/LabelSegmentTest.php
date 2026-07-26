<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\LabelSegment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class LabelSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new LabelSegment();
        self::assertSame('label', $seg->key());
        self::assertSame(90, $seg->priority());
        self::assertTrue($seg->canDegrade(0));
        self::assertTrue($seg->canDegrade(1));
        self::assertFalse($seg->canDegrade(2));
    }

    public function testEmptyLabelRendersNull(): void
    {
        self::assertNull((new LabelSegment())->render(new Task(null, ''), $this->frame(), 0));
    }

    public function testRendersStyledWhenColored(): void
    {
        $chunk = (new LabelSegment())->render(new Task(null, 'Deploy'), $this->frame(colors: 'truecolor'), 0);
        self::assertNotNull($chunk);
        self::assertSame('Deploy', $chunk->plain);
        self::assertNotNull($chunk->styled);
    }

    public function testRendersPlainWhenNotColored(): void
    {
        $chunk = (new LabelSegment())->render(new Task(null, 'Deploy'), $this->frame(colors: 'none'), 0);
        self::assertNotNull($chunk);
        self::assertNull($chunk->styled);
    }

    public function testDegradeLevelsTruncate(): void
    {
        $task = new Task(null, 'A rather long migration label here indeed');
        $lvl2 = (new LabelSegment())->render($task, $this->frame(), 2);
        self::assertNotNull($lvl2);
        self::assertLessThanOrEqual(10, Text::width($lvl2->plain));
    }

    public function testFinalTextOverridesLabel(): void
    {
        $task = new Task(null, '');
        $task->finalText = 'Schema up to date';
        $chunk = (new LabelSegment())->render($task, $this->frame(), 0);
        self::assertNotNull($chunk);
        self::assertSame('Schema up to date', $chunk->plain);
    }
}
