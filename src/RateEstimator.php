<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress;

/** Sliding-window rate estimator (tqdm-style smoothing over recent samples). */
final class RateEstimator
{
    /** @var list<array{0: float, 1: float}> [tMs, completed] */
    private array $samples = [];

    public function __construct(private readonly float $windowMs = 5000.0)
    {
    }

    public function add(float $tMs, float $completed): void
    {
        $n = \count($this->samples);
        // Coalesce hot-loop updates: advancing 10k times/sec must stay O(1).
        if ($n > 0 && $tMs - $this->samples[$n - 1][0] < 50.0) {
            $this->samples[$n - 1][1] = $completed;

            return;
        }
        $this->samples[] = [$tMs, $completed];
        $cutoff = $tMs - $this->windowMs;
        while (\count($this->samples) > 2 && $this->samples[0][0] < $cutoff) {
            array_shift($this->samples);
        }
    }

    /** Units per second, null until enough signal. */
    public function rate(): ?float
    {
        $n = \count($this->samples);
        if ($n < 2) {
            return null;
        }
        [$t0, $c0] = $this->samples[0];
        [$t1, $c1] = $this->samples[$n - 1];
        $dt = $t1 - $t0;
        if ($dt < 200.0 || $c1 <= $c0) {
            return null;
        }

        return ($c1 - $c0) / ($dt / 1000.0);
    }

    /** Seconds remaining, null if unknown. */
    public function eta(?float $total, float $completed): ?float
    {
        if ($total === null || $total <= 0) {
            return null;
        }
        $rate = $this->rate();
        if ($rate === null || $rate <= 0) {
            return null;
        }

        return max(0.0, ($total - $completed) / $rate);
    }
}
