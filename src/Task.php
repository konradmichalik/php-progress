<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress;

/** Pure progress state. Knows nothing about rendering. */
final class Task
{
    public float $completed = 0.0;
    public Status $status = Status::Idle;
    public float $startedAtMs = 0.0;
    public ?string $finalText = null;
    public readonly RateEstimator $rate;

    /** @var array<string, string> */
    private array $fields = [];
    /** @var array<string, bool> */
    private array $sticky = [];

    public function __construct(
        public ?float $total = null,
        public string $label = '',
        public string $unit = 'it',   // 'it' | 'bytes'
    ) {
        $this->rate = new RateEstimator();
    }

    public function fraction(): ?float
    {
        if ($this->total === null || $this->total <= 0) {
            return null;
        }

        return max(0.0, min(1.0, $this->completed / $this->total));
    }

    public function advance(float $n, float $nowMs): void
    {
        $this->setProgress($this->completed + $n, $nowMs);
    }

    public function setProgress(float $completed, float $nowMs): void
    {
        $this->completed = $this->total !== null ? min($completed, $this->total) : $completed;
        $this->rate->add($nowMs, $this->completed);
    }

    public function set(string $key, ?string $value, bool $sticky = false): void
    {
        if ($value === null) {
            unset($this->fields[$key], $this->sticky[$key]);

            return;
        }
        $this->fields[$key] = $value;
        if ($sticky) {
            $this->sticky[$key] = true;
        }
    }

    public function clear(string $key): void
    {
        $this->set($key, null);
    }

    /** @return array<string, string> */
    public function fields(bool $onlySticky = false): array
    {
        if (!$onlySticky) {
            return $this->fields;
        }

        return array_intersect_key($this->fields, $this->sticky);
    }

    public function hasStickyFields(): bool
    {
        return $this->sticky !== [];
    }

    public function elapsedSeconds(float $nowMs): float
    {
        return $this->startedAtMs > 0 ? max(0.0, ($nowMs - $this->startedAtMs) / 1000.0) : 0.0;
    }
}
