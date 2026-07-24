<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress;

use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

/** Per-render context: wall clock drives all animation, never the progress value. */
final class Frame
{
    public function __construct(
        public readonly float $nowMs,
        public readonly int $width,
        public readonly Capabilities $caps,
        public readonly Theme $theme,
    ) {
    }

    public function colored(): bool
    {
        return $this->caps->colors !== 'none';
    }
}
