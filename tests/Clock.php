<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

/**
 * A deterministic, injectable clock (milliseconds). Advance it explicitly with
 * `$clock->ms += 100` so animation and throttling are fully under test control.
 */
final class Clock
{
    public function __construct(public float $ms = 0.0)
    {
    }

    public function __invoke(): float
    {
        return $this->ms;
    }
}
