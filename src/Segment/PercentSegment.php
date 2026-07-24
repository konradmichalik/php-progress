<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

final class PercentSegment implements Segment
{
    public function key(): string
    {
        return 'percent';
    }

    public function priority(): int
    {
        return 95;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        $frac = $task->fraction();
        if ($frac === null) {
            return null;
        }
        // Fixed 4-char width so the bar never shifts when crossing 9% -> 10% -> 100%.
        $plain = sprintf('%3d%%', (int) floor($frac * 100));
        $styled = $frame->colored()
            ? Ansi::BOLD . Ansi::fg(Ansi::hex($frame->theme->accent), $frame->caps->colors) . $plain . Ansi::RESET
            : null;

        return new Chunk($plain, $styled);
    }
}
