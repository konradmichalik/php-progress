<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

/**
 * Leading spinner. Two rendering modes share the same slot and finished-icon
 * behaviour:
 *   - frame-based: cycle a list of glyphs on a wall-clock interval;
 *   - procedural: a closure (phase 0..1, Frame) -> string, animated from the
 *     wall clock over a period. The string may carry ANSI; width is derived
 *     from its plain form so the layout stays exact.
 */
final class SpinnerSegment implements Segment
{
    /**
     * @param list<string> $frames
     * @param (\Closure(float, Frame): string)|null $proc
     */
    public function __construct(
        private readonly array $frames,
        private readonly int $intervalMs,
        private readonly ?string $color = null,
        private readonly ?\Closure $proc = null,
        private readonly int $periodMs = 1000,
    ) {
    }

    public function key(): string
    {
        return 'spinner';
    }

    public function priority(): int
    {
        return 96;
    }

    public function canDegrade(int $level): bool
    {
        return false;
    }

    public function render(Task $task, Frame $frame, int $level): Chunk
    {
        $th = $frame->theme;
        $depth = $frame->caps->colors;

        if ($task->status->finished()) {
            [$icon, $hex] = match ($task->status) {
                Status::Success => [$th->iconSuccess, $th->good],
                Status::Failure => [$th->iconFailure, $th->bad],
                Status::Warning => [$th->iconWarning, $th->warn],
                default => [$th->iconStopped, $th->muted],
            };
            $styled = $frame->colored()
                ? Ansi::fg(Ansi::hex($hex), $depth) . $icon . Ansi::RESET
                : null;

            return new Chunk($icon, $styled);
        }

        if ($this->proc !== null) {
            $phase = $this->periodMs > 0
                ? fmod($frame->nowMs, (float) $this->periodMs) / $this->periodMs
                : 0.0;
            $out = ($this->proc)($phase, $frame);
            $plain = Text::stripAnsi($out);

            return new Chunk($plain, $frame->colored() ? $out : $plain);
        }

        // Defensive: this class is public and reachable via columns(). An empty
        // frame list (or non-positive interval) must degrade to an empty slot,
        // never a fatal modulo/division by zero.
        $count = \count($this->frames);
        if ($count === 0 || $this->intervalMs <= 0) {
            return new Chunk('', $frame->colored() ? '' : null);
        }
        $idx = (int) floor($frame->nowMs / $this->intervalMs) % $count;
        $ch = $this->frames[$idx];
        $hex = $this->color ?? $th->accent;
        $styled = $frame->colored()
            ? Ansi::fg(Ansi::hex($hex), $depth) . $ch . Ansi::RESET
            : null;

        return new Chunk($ch, $styled);
    }
}
