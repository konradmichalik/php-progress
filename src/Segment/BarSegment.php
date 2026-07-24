<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\FlexSegment;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

/**
 * Sub-cell smooth bar: full blocks + 1/8 partials, truecolor gradient,
 * wall-clock shimmer. Falls back to a travelling pulse when total is unknown.
 */
final class BarSegment implements FlexSegment
{
    public function key(): string
    {
        return 'bar';
    }

    public function priority(): int
    {
        return 100;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function minWidth(): int
    {
        return 6;
    }

    public function render(Task $task, Frame $frame, int $level): Chunk
    {
        return $this->renderFlex($task, $frame, $this->minWidth());
    }

    public function renderFlex(Task $task, Frame $frame, int $width): Chunk
    {
        $frac = $task->fraction();
        if ($frac === null) {
            return $this->pulse($task, $frame, $width);
        }

        $th = $frame->theme;
        $depth = $frame->caps->colors;
        $colored = $frame->colored();
        $from = Ansi::hex($th->gradientFrom);
        $to = Ansi::hex($th->gradientTo);
        $mutedRgb = Ansi::hex($th->muted);
        $white = [255, 255, 255];

        $exact = $frac * $width;
        $full = (int) floor($exact);
        $eighth = $th->eighths !== [] ? (int) floor(($exact - $full) * 8) : 0;

        $shimmer = $th->shimmer && $colored && $task->status === Status::Running && $frac < 1.0;
        $shimmerPos = fmod($frame->nowMs / 16.0, $width + 24.0) - 12.0;

        $plain = '';
        $styled = '';
        for ($i = 0; $i < $width; $i++) {
            if ($i < $full || ($i === $full && $eighth > 0)) {
                $ch = $i < $full ? $th->doneChar : $th->eighths[$eighth - 1];
                $plain .= $ch;
                if ($colored) {
                    $rgb = Ansi::mix($from, $to, $width > 1 ? $i / ($width - 1) : 0.0);
                    if ($shimmer) {
                        $d = $i - $shimmerPos;
                        $boost = exp(-($d * $d) / 9.0) * 0.55;
                        if ($boost > 0.02) {
                            $rgb = Ansi::mix($rgb, $white, $boost);
                        }
                    }
                    $styled .= Ansi::fg($rgb, $depth) . $ch;
                }
            } else {
                $plain .= $th->trackChar;
                if ($colored) {
                    $styled .= Ansi::fg($mutedRgb, $depth) . $th->trackChar;
                }
            }
        }
        if ($colored) {
            $styled .= Ansi::RESET;
        }

        return new Chunk($plain, $colored ? $styled : null);
    }

    private function pulse(Task $task, Frame $frame, int $width): Chunk
    {
        $th = $frame->theme;
        $depth = $frame->caps->colors;
        $colored = $frame->colored();
        $accent = Ansi::hex($th->accent);
        $mutedRgb = Ansi::hex($th->muted);

        $tail = max(4.0, $width / 4.0);
        $pos = $task->status === Status::Running
            ? fmod($frame->nowMs / 22.0, (float) $width)
            : 0.0;

        $plain = '';
        $styled = '';
        for ($i = 0; $i < $width; $i++) {
            $d = fmod($pos - $i + $width, (float) $width);
            $t = 1.0 - min(1.0, $d / $tail);
            if ($t > 0.05) {
                $plain .= $th->doneChar;
                if ($colored) {
                    $rgb = Ansi::mix($mutedRgb, $accent, $t);
                    $styled .= Ansi::fg($rgb, $depth) . $th->doneChar;
                }
            } else {
                $plain .= $th->trackChar;
                if ($colored) {
                    $styled .= Ansi::fg($mutedRgb, $depth) . $th->trackChar;
                }
            }
        }
        if ($colored) {
            $styled .= Ansi::RESET;
        }

        return new Chunk($plain, $colored ? $styled : null);
    }
}
