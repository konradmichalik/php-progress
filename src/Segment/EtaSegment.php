<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

final class EtaSegment implements Segment
{
    public function key(): string
    {
        return 'eta';
    }

    public function priority(): int
    {
        return 45;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        if ($task->status->finished()) {
            return null;
        }
        $eta = $task->rate->eta($task->total, $task->completed);
        if ($eta === null) {
            return null;
        }
        $plain = 'eta ' . Text::duration($eta);
        $styled = $frame->colored() ? Ansi::DIM . $plain . Ansi::RESET : null;

        return new Chunk($plain, $styled);
    }
}
