<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Render;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Task;

/**
 * A segment is a pure function (Task, Frame) -> Chunk.
 * Layout may raise its degrade $level under width pressure before dropping it.
 */
interface Segment
{
    public function key(): string;

    /** Higher survives longer. >= 95 is never dropped (may still degrade). */
    public function priority(): int;

    /** Whether one more degrade level exists beyond $level. */
    public function canDegrade(int $level): bool;

    /** Null = segment absent this frame (its separator collapses too). */
    public function render(Task $task, Frame $frame, int $level): ?Chunk;
}
