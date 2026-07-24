<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

final class ElapsedSegment implements Segment
{
    public function key(): string
    {
        return 'elapsed';
    }

    public function priority(): int
    {
        return 30;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        if ($task->startedAtMs <= 0) {
            return null;
        }
        $plain = '(' . Text::duration($task->elapsedSeconds($frame->nowMs)) . ')';
        $styled = $frame->colored() ? Ansi::DIM . $plain . Ansi::RESET : null;

        return new Chunk($plain, $styled);
    }
}
