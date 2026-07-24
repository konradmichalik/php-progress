<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

final class RateSegment implements Segment
{
    public function key(): string
    {
        return 'rate';
    }

    public function priority(): int
    {
        return 40;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        $rate = $task->rate->rate();
        if ($rate === null) {
            return null;
        }
        $plain = $task->unit === 'bytes'
            ? Text::bytes($rate) . '/s'
            : ($rate >= 100 ? sprintf('%d it/s', (int) $rate) : sprintf('%.1f it/s', $rate));
        $styled = $frame->colored() ? Ansi::DIM . $plain . Ansi::RESET : null;

        return new Chunk($plain, $styled);
    }
}
