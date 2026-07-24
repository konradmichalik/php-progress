<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress;

use KonradMichalik\PhpProgress\Render\Layout;
use KonradMichalik\PhpProgress\Render\Renderer;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Segment\BarSegment;
use KonradMichalik\PhpProgress\Segment\CountSegment;
use KonradMichalik\PhpProgress\Segment\ElapsedSegment;
use KonradMichalik\PhpProgress\Segment\EtaSegment;
use KonradMichalik\PhpProgress\Segment\FieldsSegment;
use KonradMichalik\PhpProgress\Segment\LabelSegment;
use KonradMichalik\PhpProgress\Segment\PercentSegment;
use KonradMichalik\PhpProgress\Segment\RateSegment;
use KonradMichalik\PhpProgress\Segment\SpinnerSegment;
use KonradMichalik\PhpProgress\Style\Spinners;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

/**
 * The user-facing handle: one dynamic live line.
 * Bar and spinner are just different default column sets over the same engine.
 */
final class Live
{
    private Task $task;
    private ?Renderer $renderer = null;
    private ?Layout $layout = null;

    /** @var list<string|Segment> */
    private array $columns;
    /** @var resource */
    private $stream;
    private ?Capabilities $caps = null;
    private ?Theme $theme = null;
    private float $fps = 12.0;
    private int $barWidth = 40;
    private bool $expand = false;
    private bool $transient = false;
    private bool $handleSignals = true;
    private string $spinnerStyle = 'dots';
    /** @var array{frames: list<string>, interval: int}|null */
    private ?array $customFrames = null;
    /** @var (\Closure(float, \KonradMichalik\PhpProgress\Frame): string)|null */
    private ?\Closure $spinnerFn = null;
    private int $spinnerPeriodMs = 1000;
    private ?string $spinnerColor = null;
    /** @var (callable(): float)|null */
    private $clock = null;

    /** @param list<string|Segment> $columns */
    private function __construct(?float $total, string $label, array $columns)
    {
        $this->task = new Task($total, $label);
        $this->columns = $columns;
        $stream = \defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
        $this->stream = \is_resource($stream) ? $stream : throw new \RuntimeException('php-progress: unable to open STDERR');
    }

    public static function bar(?float $total = null, string $label = ''): self
    {
        return new self($total, $label, ['label', 'bar', 'percent', 'count', 'fields', 'rate', 'eta']);
    }

    public static function spinner(string $label = ''): self
    {
        return new self(null, $label, ['spinner', 'label', 'fields', 'elapsed']);
    }

    // ---- configuration (before start) ---------------------------------

    /**
     * Accepts a stream resource or any object exposing getStream()
     * (e.g. Symfony's StreamOutput / TYPO3 command output).
     *
     * @param resource|object $target
     */
    public function to($target): self
    {
        if (\is_object($target) && method_exists($target, 'getStream')) {
            $target = $target->getStream();
        }
        if (!\is_resource($target)) {
            throw new \InvalidArgumentException('to() expects a stream resource or an object with getStream()');
        }
        $this->stream = $target;

        return $this;
    }

    /** Column names ('label', 'bar', ...) and/or custom Segment instances, in order. */
    public function columns(string|Segment ...$columns): self
    {
        $this->columns = array_values($columns);

        return $this;
    }

    public function theme(Theme $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function fps(float $fps): self
    {
        $this->fps = max(1.0, $fps);

        return $this;
    }

    public function barWidth(int $width): self
    {
        $this->barWidth = max(3, $width);

        return $this;
    }

    public function expand(): self
    {
        $this->expand = true;

        return $this;
    }

    /** Clear the line on finish instead of persisting it. */
    public function transient(): self
    {
        $this->transient = true;

        return $this;
    }

    /**
     * Opt out of php-progress's SIGINT/SIGTERM handlers (ext-pcntl). Disable this if
     * your application installs its own signal handling; you are then
     * responsible for restoring the cursor on interruption.
     */
    public function handleSignals(bool $handle = true): self
    {
        $this->handleSignals = $handle;

        return $this;
    }

    public function unit(string $unit): self
    {
        $this->task->unit = $unit;

        return $this;
    }

    public function style(string $name): self
    {
        $this->spinnerStyle = $name;

        return $this;
    }

    /** @param list<string> $frames */
    public function frames(array $frames, int $intervalMs = 100): self
    {
        if ($frames === []) {
            throw new \InvalidArgumentException('frames() requires at least one frame');
        }
        $this->customFrames = Spinners::normalize($frames, $intervalMs);

        return $this;
    }

    /**
     * Drive the spinner procedurally: a closure (phase 0..1, Frame) -> string,
     * animated from the wall clock over $periodMs. The returned string may carry
     * ANSI colour; its plain width must stay constant across phases. This is the
     * same idea the bar uses for shimmer and pulse.
     *
     * @param \Closure(float, \KonradMichalik\PhpProgress\Frame): string $fn
     */
    public function spinnerFn(\Closure $fn, int $periodMs = 1000): self
    {
        $this->spinnerFn = $fn;
        $this->spinnerPeriodMs = max(10, $periodMs);

        return $this;
    }

    public function color(string $hex): self
    {
        $this->spinnerColor = $hex;

        return $this;
    }

    /** @internal test/bridge hook */
    public function caps(Capabilities $caps): self
    {
        $this->caps = $caps;

        return $this;
    }

    /** @internal test hook: inject a deterministic clock (ms) */
    public function clock(callable $clock): self
    {
        $this->clock = $clock;

        return $this;
    }

    // ---- lifecycle -----------------------------------------------------

    public function start(): self
    {
        if ($this->renderer !== null) {
            return $this;
        }
        $refreshWidth = $this->caps === null;
        $caps = $this->caps ?? Capabilities::detect($this->stream);
        $theme = $this->theme ?? ($caps->unicode ? new Theme() : Theme::ascii());
        $this->renderer = new Renderer($this->stream, $caps, $theme, $this->fps, $this->clock, $refreshWidth, $this->handleSignals);
        $this->layout = new Layout($this->buildSegments($caps, $theme), $this->barWidth, $this->expand);

        $this->task->status = Status::Running;
        $this->task->startedAtMs = $this->renderer->nowMs();
        $this->renderer->start();
        $this->renderer->draw($this->task, $this->layout, force: true);

        return $this;
    }

    public function advance(float $n = 1.0): self
    {
        $this->ensureStarted();
        $this->task->advance($n, $this->now());
        $this->paint();

        return $this;
    }

    public function progress(float $completed): self
    {
        $this->ensureStarted();
        $this->task->setProgress($completed, $this->now());
        $this->paint();

        return $this;
    }

    public function total(?float $total): self
    {
        $this->task->total = $total;

        return $this;
    }

    /** Keep animations alive during phases without progress updates. */
    public function tick(): self
    {
        $this->ensureStarted();
        $this->paint();

        return $this;
    }

    /** Show extra info on the line; set(key, null) removes it again. */
    public function set(string $key, ?string $value, bool $sticky = false): self
    {
        $this->ensureStarted();
        $this->task->set($key, $value, $sticky);
        $this->paint();

        return $this;
    }

    public function clear(string $key): self
    {
        return $this->set($key, null);
    }

    public function text(string $label): self
    {
        $this->ensureStarted();
        $this->task->label = $label;
        $this->paint();

        return $this;
    }

    /** Print a normal log line above the live line. */
    public function println(string $message): self
    {
        $this->ensureStarted();
        if ($this->renderer !== null && $this->layout !== null) {
            $this->renderer->println($message, $this->task, $this->layout);
        }

        return $this;
    }

    public function succeed(?string $text = null): void
    {
        $this->end(Status::Success, $text);
    }

    public function fail(?string $text = null): void
    {
        $this->end(Status::Failure, $text);
    }

    public function warn(?string $text = null): void
    {
        $this->end(Status::Warning, $text);
    }

    public function stop(?string $text = null): void
    {
        $this->end(Status::Stopped, $text);
    }

    /** Complete: fills the bar and persists as success. */
    public function finish(?string $text = null): void
    {
        if ($this->task->total !== null) {
            $this->task->setProgress($this->task->total, $this->now());
        }
        $this->end(Status::Success, $text);
    }

    /**
     * Run a callable under this live line: succeed on return, fail on throw.
     *
     *   Progress::spinner('Deploying')->run(fn () => deploy());
     */
    public function run(callable $fn): mixed
    {
        $this->start();
        try {
            $result = $fn($this);
            if (!$this->task->status->finished()) {
                $this->succeed();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$this->task->status->finished()) {
                $this->fail();
            }
            throw $e;
        }
    }

    public function task(): Task
    {
        return $this->task;
    }

    /** Safety net: a forgotten finish() must never leave a hidden cursor behind. */
    public function __destruct()
    {
        if ($this->renderer !== null && !$this->task->status->finished()) {
            $this->stop();
        }
    }

    private function ensureStarted(): void
    {
        if ($this->renderer === null) {
            $this->start();
        }
    }

    private function end(Status $status, ?string $text): void
    {
        if ($this->task->status->finished()) {
            return;
        }
        $this->task->status = $status;
        $this->task->finalText = $text;
        if ($this->renderer !== null && $this->layout !== null) {
            $this->renderer->finish($this->task, $this->layout, $this->transient);
        }
    }

    private function now(): float
    {
        return $this->renderer?->nowMs() ?? microtime(true) * 1000.0;
    }

    /** Single guarded repaint: renderer and layout are always set as a pair in start(). */
    private function paint(bool $force = false): void
    {
        if ($this->renderer !== null && $this->layout !== null) {
            $this->renderer->draw($this->task, $this->layout, $force);
        }
    }

    /** @return list<Segment> */
    private function buildSegments(Capabilities $caps, Theme $theme): array
    {
        $spinnerSegment = $this->resolveSpinner($caps);

        $registry = [
            'label' => static fn (): Segment => new LabelSegment(),
            'spinner' => static fn (): Segment => $spinnerSegment,
            'bar' => static fn (): Segment => new BarSegment(),
            'percent' => static fn (): Segment => new PercentSegment(),
            'count' => static fn (): Segment => new CountSegment(),
            'fields' => static fn (): Segment => new FieldsSegment(),
            'rate' => static fn (): Segment => new RateSegment(),
            'eta' => static fn (): Segment => new EtaSegment(),
            'elapsed' => static fn (): Segment => new ElapsedSegment(),
        ];

        $segments = [];
        foreach ($this->columns as $column) {
            if ($column instanceof Segment) {
                $segments[] = $column;
            } elseif (isset($registry[$column])) {
                $segments[] = $registry[$column]();
            }
        }

        return $segments;
    }

    /**
     * Precedence: explicit closure > explicit frames > procedural style >
     * frame style (with ASCII fallback on non-UTF-8 terminals).
     */
    private function resolveSpinner(Capabilities $caps): SpinnerSegment
    {
        if ($this->spinnerFn !== null) {
            return new SpinnerSegment([], 100, $this->spinnerColor, $this->spinnerFn, $this->spinnerPeriodMs);
        }
        if ($this->customFrames !== null) {
            return new SpinnerSegment($this->customFrames['frames'], $this->customFrames['interval'], $this->spinnerColor);
        }
        if ($caps->unicode && Spinners::isProcedural($this->spinnerStyle)) {
            $proc = Spinners::procedural($this->spinnerStyle, $this->spinnerColor);
            if ($proc !== null) {
                return new SpinnerSegment([], 100, $this->spinnerColor, $proc['fn'], $proc['period']);
            }
        }
        $styleName = $caps->unicode ? $this->spinnerStyle : Spinners::asciiName($this->spinnerStyle);
        $spinner = Spinners::get($styleName);

        return new SpinnerSegment($spinner['frames'], $spinner['interval'], $this->spinnerColor);
    }
}
