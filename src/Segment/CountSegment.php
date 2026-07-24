<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

final class CountSegment implements Segment
{
    public function key(): string
    {
        return 'count';
    }

    public function priority(): int
    {
        return 50;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        if ($task->total === null) {
            return null;
        }
        if ($task->unit === 'bytes') {
            $plain = Text::bytes($task->completed) . '/' . Text::bytes($task->total);
        } else {
            $digits = \strlen((string) (int) $task->total);
            $plain = sprintf("%{$digits}d/%d", (int) $task->completed, (int) $task->total);
        }
        $styled = $frame->colored() ? Ansi::DIM . $plain . Ansi::RESET : null;

        return new Chunk($plain, $styled);
    }
}
