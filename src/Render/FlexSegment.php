<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Render;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Task;

/** The one segment that absorbs remaining width (the bar). */
interface FlexSegment extends Segment
{
    public function minWidth(): int;

    public function renderFlex(Task $task, Frame $frame, int $width): Chunk;
}
