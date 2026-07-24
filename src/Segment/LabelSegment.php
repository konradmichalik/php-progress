<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

final class LabelSegment implements Segment
{
    private const CAPS = [30, 16, 10];

    public function key(): string
    {
        return 'label';
    }

    public function priority(): int
    {
        return 90;
    }

    public function canDegrade(int $level): bool
    {
        return $level < \count(self::CAPS) - 1;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        // Guard on the *resolved* text: a spinner started without a label but
        // finished with succeed('done') still has a final message to show.
        $text = Text::sanitize($task->finalText ?? $task->label);
        if ($text === '') {
            return null;
        }
        $cap = self::CAPS[min($level, \count(self::CAPS) - 1)];
        $plain = Text::truncateEnd($text, $cap);
        $styled = $frame->colored() ? Ansi::BOLD . $plain . Ansi::RESET : null;

        return new Chunk($plain, $styled);
    }
}
