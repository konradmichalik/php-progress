<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Segment;

use KonradMichalik\PhpProgress\Segment\FieldsSegment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class FieldsSegmentTest extends TestCase
{
    public function testMetadata(): void
    {
        $seg = new FieldsSegment();
        self::assertSame('fields', $seg->key());
        self::assertSame(60, $seg->priority());
        self::assertTrue($seg->canDegrade(0));
        self::assertFalse($seg->canDegrade(1));
    }

    public function testNullWithoutFields(): void
    {
        self::assertNull((new FieldsSegment())->render(new Task(null), $this->frame(), 0));
    }

    public function testStickyAndExtraBothRenderWithinBudget(): void
    {
        $task = new Task(null);
        $task->set('host', 'db01', sticky: true);
        $task->set('file', 'intro.mp4');
        $chunk = (new FieldsSegment())->render($task, $this->frame(width: 200, colors: 'truecolor'), 0);
        self::assertNotNull($chunk);
        self::assertStringContainsString('host db01', $chunk->plain);
        self::assertStringContainsString('file intro.mp4', $chunk->plain);
        self::assertNotNull($chunk->styled);
    }

    public function testDegradeLevelKeepsOnlySticky(): void
    {
        $task = new Task(null);
        $task->set('host', 'db01', sticky: true);
        $task->set('file', 'intro.mp4');
        $chunk = (new FieldsSegment())->render($task, $this->frame(width: 200), 1);
        self::assertNotNull($chunk);
        self::assertStringContainsString('host db01', $chunk->plain);
        self::assertStringNotContainsString('intro.mp4', $chunk->plain);
    }

    public function testPlainWhenNotColored(): void
    {
        $task = new Task(null);
        $task->set('host', 'db01');
        $chunk = (new FieldsSegment())->render($task, $this->frame(width: 200, colors: 'none'), 0);
        self::assertNotNull($chunk);
        self::assertNull($chunk->styled);
    }

    public function testLoneOverLongValueIsSqueezed(): void
    {
        $task = new Task(null);
        $task->set('k', str_repeat('x', 40)); // > VALUE_MAX, and > cap at narrow width
        $chunk = (new FieldsSegment())->render($task, $this->frame(width: 48), 0); // cap = 16
        self::assertNotNull($chunk);
        self::assertLessThanOrEqual(16, Text::width($chunk->plain));
        self::assertStringContainsString('…', $chunk->plain);
    }

    public function testLoneOverLongKeyLeavesNoRoom(): void
    {
        $task = new Task(null);
        $task->set(str_repeat('k', 14), 'value'); // room = 16 - 14 - 1 = 1 < 4
        self::assertNull((new FieldsSegment())->render($task, $this->frame(width: 48), 0));
    }

    public function testSecondPairWaitsWhenItDoesNotFit(): void
    {
        $task = new Task(null);
        $task->set('a', 'bb');                       // fits
        $task->set('c', str_repeat('d', 15));        // pushes over cap 16
        $chunk = (new FieldsSegment())->render($task, $this->frame(width: 48), 0);
        self::assertNotNull($chunk);
        self::assertStringContainsString('a bb', $chunk->plain);
        self::assertStringNotContainsString('c ', $chunk->plain);
    }
}
