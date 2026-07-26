<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Render;

use KonradMichalik\PhpProgress\Render\Layout;
use KonradMichalik\PhpProgress\Segment\BarSegment;
use KonradMichalik\PhpProgress\Segment\CountSegment;
use KonradMichalik\PhpProgress\Segment\ElapsedSegment;
use KonradMichalik\PhpProgress\Segment\FieldsSegment;
use KonradMichalik\PhpProgress\Segment\LabelSegment;
use KonradMichalik\PhpProgress\Segment\PercentSegment;
use KonradMichalik\PhpProgress\Segment\SpinnerSegment;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Tests\TestCase;

final class LayoutTest extends TestCase
{
    private const BAR_GLYPHS = '/[\x{2500}\x{2588}-\x{258F}]+/u';

    public function testComposeFitsAndRendersEssentials(): void
    {
        $layout = new Layout([new LabelSegment(), new BarSegment(), new PercentSegment()]);
        $task = new Task(100.0, 'Deploy');
        $task->setProgress(67.0, 0.0);
        $line = $layout->compose($task, $this->frame(80, 'none'));
        self::assertStringContainsString('Deploy', $line);
        self::assertStringContainsString('67%', $line);
        self::assertLessThanOrEqual(80, Text::width(Text::stripAnsi($line)));
    }

    public function testNullSegmentIsSkipped(): void
    {
        // Count returns null when total is null; the layout just omits it.
        $layout = new Layout([new LabelSegment(), new BarSegment(), new CountSegment()]);
        $line = $layout->compose(new Task(null, 'x'), $this->frame(60, 'none'));
        self::assertStringNotContainsString('/', $line);
    }

    public function testLayoutWithoutFlexSegment(): void
    {
        // Spinner preset has no bar (FlexSegment); the flex-null branch must hold.
        $task = new Task(null, 'Connecting');
        $task->status = Status::Running;
        $task->startedAtMs = 0.0;
        $layout = new Layout([new SpinnerSegment(['a'], 100), new LabelSegment(), new ElapsedSegment()]);
        $line = $layout->compose($task, $this->frame(60, 'none', nowMs: 1000.0));
        self::assertStringContainsString('Connecting', $line);
        self::assertLessThanOrEqual(60, Text::width(Text::stripAnsi($line)));
    }

    public function testNoFrameExceedsTerminalAcrossWidths(): void
    {
        $layout = new Layout([
            new LabelSegment(), new BarSegment(), new PercentSegment(),
            new CountSegment(), new FieldsSegment(),
        ]);
        $task = new Task(100.0, 'A very long migration label that will not fit');
        $task->setProgress(67.0, 0.0);
        $task->set('mode', 'IRRE', sticky: true);
        foreach ([100, 80, 64, 46, 34, 26, 12, 5] as $width) {
            $line = $layout->compose($task, $this->frame($width, 'truecolor'));
            self::assertLessThanOrEqual(
                $width,
                Text::width(Text::stripAnsi($line)),
                "width {$width} must not overflow",
            );
        }
    }

    public function testBarReachesComfortWidthWhileSlackRemains(): void
    {
        $layout = new Layout([
            new LabelSegment(), new BarSegment(), new PercentSegment(),
            new CountSegment(), new FieldsSegment(),
        ]);
        $task = new Task(100.0, 'A rather long migration label');
        $task->setProgress(67.0, 0.0);
        $task->set('mode', 'IRRE', sticky: true);

        foreach ([90, 64] as $width) {
            $line = Text::stripAnsi($layout->compose($task, $this->frame($width, 'none')));
            // slack present (full label kept) => bar must not starve below comfort
            preg_match(self::BAR_GLYPHS, $line, $m);
            self::assertGreaterThanOrEqual(12, Text::width($m[0] ?? ''), "bar comfort at {$width}");
        }
    }

    public function testStickyFieldSurvivesWhileNonStickyIsDropped(): void
    {
        $layout = new Layout([
            new LabelSegment(), new BarSegment(), new PercentSegment(), new FieldsSegment(),
        ]);
        $task = new Task(100.0, 'Import');
        $task->setProgress(50.0, 0.0);
        $task->set('host', 'db01', sticky: true);
        $task->set('debug', 'irre-children-recheck-pass-2');
        $line = Text::stripAnsi($layout->compose($task, $this->frame(44, 'none')));
        self::assertStringContainsString('db01', $line);
        self::assertStringNotContainsString('irre-children', $line);
    }

    public function testPercentSurvivesUndroppable(): void
    {
        $layout = new Layout([
            new LabelSegment(), new BarSegment(), new PercentSegment(), new CountSegment(),
        ]);
        $task = new Task(1000.0, 'A very long label here');
        $task->setProgress(250.0, 0.0);
        $line = Text::stripAnsi($layout->compose($task, $this->frame(34, 'none')));
        self::assertStringContainsString('%', $line);
        self::assertStringNotContainsString('/1000', $line); // count dropped first
    }

    public function testClampWhenUndroppableColumnsExceedTerminal(): void
    {
        $layout = new Layout([new BarSegment(), new PercentSegment()]);
        $task = new Task(100.0, '');
        $task->setProgress(50.0, 0.0);
        $line = $layout->compose($task, $this->frame(5, 'truecolor'));
        self::assertLessThanOrEqual(5, Text::width(Text::stripAnsi($line)));
    }

    public function testExpandFillsTerminalWidth(): void
    {
        $layout = new Layout([new BarSegment()], flexMax: 40, expand: true);
        $task = new Task(100.0);
        $task->setProgress(50.0, 0.0);
        $line = Text::stripAnsi($layout->compose($task, $this->frame(100, 'none')));
        preg_match(self::BAR_GLYPHS, $line, $m);
        self::assertSame(100, Text::width($m[0] ?? ''));
    }

    public function testBarWidthCappedByFlexMaxWithoutExpand(): void
    {
        $layout = new Layout([new BarSegment()], flexMax: 20, expand: false);
        $task = new Task(100.0);
        $task->setProgress(50.0, 0.0);
        $line = Text::stripAnsi($layout->compose($task, $this->frame(100, 'none')));
        preg_match(self::BAR_GLYPHS, $line, $m);
        self::assertSame(20, Text::width($m[0] ?? ''));
    }
}
