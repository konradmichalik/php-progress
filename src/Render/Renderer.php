<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Render;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Status;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

/**
 * The single writer. TTY: throttled repaint-in-place + OSC 9;4 taskbar progress.
 * No TTY (CI, pipes): periodic plain-text status lines instead of ANSI noise.
 */
final class Renderer
{
    private float $lastRenderMs = -INF;
    private float $lastLogMs = -INF;
    private int $lastLogDecile = -1;
    private bool $active = false;
    private int $width;
    private float $lastWidthCheckMs = -INF;
    private int $lastLineWidth = 0;

    /** @var \Closure(): float */
    private \Closure $clock;

    private static bool $shutdownRegistered = false;
    private static bool $signalsInstalled = false;
    /** @var array<int, resource> */
    private static array $cursorStreams = [];

    /**
     * @param resource $stream
     * @param (callable(): float)|null $clock
     */
    public function __construct(
        private $stream,
        private readonly Capabilities $caps,
        private readonly Theme $theme,
        private float $fps = 12.0,
        ?callable $clock = null,
        private readonly bool $refreshWidth = false,
        private readonly bool $handleSignals = true,
    ) {
        $this->width = $caps->width;
        $this->clock = $clock !== null
            ? \Closure::fromCallable($clock)
            : static fn (): float => microtime(true) * 1000.0;
    }

    public function nowMs(): float
    {
        return ($this->clock)();
    }

    public function caps(): Capabilities
    {
        return $this->caps;
    }

    public function start(): void
    {
        $this->active = true;
        if ($this->caps->tty) {
            $this->write(Ansi::HIDE_CURSOR);
            self::$cursorStreams[(int) $this->stream] = $this->stream;
            if (!self::$shutdownRegistered) {
                self::$shutdownRegistered = true;
                register_shutdown_function(static function (): void {
                    self::restoreTerminal();
                });
            }
            if ($this->handleSignals) {
                self::installSignalHandlers();
            }
        }
    }

    /** Restore cursor + clear taskbar progress for every live stream. Idempotent. */
    private static function restoreTerminal(): void
    {
        foreach (self::$cursorStreams as $s) {
            if (\is_resource($s)) {
                @fwrite($s, Ansi::osc94(0) . Ansi::SHOW_CURSOR);
            }
        }
        self::$cursorStreams = [];
    }

    /**
     * A default SIGINT (Ctrl+C) terminates the process without running shutdown
     * functions, so the hidden cursor would leak. With ext-pcntl we catch
     * SIGINT/SIGTERM, restore the terminal, then re-raise with the default
     * disposition to preserve the 128+signo exit code.
     */
    private static function installSignalHandlers(): void
    {
        if (self::$signalsInstalled || !\function_exists('pcntl_signal') || !\function_exists('pcntl_async_signals')) {
            return;
        }
        self::$signalsInstalled = true;

        $handler = static function (int $signo): void {
            self::restoreTerminal();
            if (\function_exists('pcntl_signal')) {
                pcntl_signal($signo, SIG_DFL);
            }
            if (\function_exists('posix_kill') && \function_exists('posix_getpid')) {
                posix_kill(posix_getpid(), $signo);
            } else {
                exit(128 + $signo);
            }
        };

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }

    public function draw(Task $task, Layout $layout, bool $force = false): void
    {
        if (!$this->active) {
            return;
        }
        $now = $this->nowMs();

        if (!$this->caps->tty) {
            $this->logPlain($task, $now, $force);

            return;
        }

        if (!$force && ($now - $this->lastRenderMs) < 1000.0 / $this->fps) {
            return;
        }
        $this->lastRenderMs = $now;

        $frame = new Frame($now, $this->currentWidth($now), $this->caps, $this->theme);
        $line = $layout->compose($task, $frame);
        $this->paintLine($line, $task);
    }

    /**
     * Repaint in place without a blank intermediate state: return to column 0,
     * overwrite the line, then erase only the leftover tail if the new line is
     * shorter than the previous one. This avoids the erase-then-write flicker
     * visible on slow terminals / SSH at higher refresh rates.
     */
    private function paintLine(string $line, Task $task): void
    {
        $visible = Text::width(Text::stripAnsi($line));
        $out = Ansi::CR . $line;
        if ($visible < $this->lastLineWidth) {
            $out .= Ansi::ERASE_TO_END;
        }
        $this->lastLineWidth = $visible;
        $this->write($out . $this->osc($task));
    }

    /** Print a normal log line above the live line, then let the next draw repaint. */
    public function println(string $message, Task $task, Layout $layout): void
    {
        $message = Text::sanitize($message);
        if ($this->caps->tty && $this->active) {
            $this->write(Ansi::ERASE_LINE . $message . "\n");
            $this->lastLineWidth = 0;
            $this->draw($task, $layout, force: true);

            return;
        }
        $this->write($message . "\n");
    }

    public function finish(Task $task, Layout $layout, bool $transient): void
    {
        if (!$this->active) {
            return;
        }
        if ($this->caps->tty) {
            if ($transient) {
                $this->write(Ansi::ERASE_LINE);
            } else {
                $this->draw($task, $layout, force: true);
                $this->write("\n");
            }
            $this->lastLineWidth = 0;
            $this->write(Ansi::osc94(0) . Ansi::SHOW_CURSOR);
            unset(self::$cursorStreams[(int) $this->stream]);
        } else {
            $this->logPlain($task, $this->nowMs(), force: true);
        }
        $this->active = false;
    }

    private function write(string $data): void
    {
        if (\is_resource($this->stream)) {
            @fwrite($this->stream, $data);
        }
    }

    /** Follow terminal resizes with <= 2s lag (cheaper + more portable than SIGWINCH). */
    private function currentWidth(float $now): int
    {
        if ($this->refreshWidth && $this->caps->tty && $now - $this->lastWidthCheckMs >= 2000.0) {
            $this->lastWidthCheckMs = $now;
            $this->width = Capabilities::detectWidth(true);
        }

        return $this->width;
    }

    private function osc(Task $task): string
    {
        $frac = $task->fraction();

        return $frac === null
            ? Ansi::osc94(3)
            : Ansi::osc94(1, (int) floor($frac * 100));
    }

    private function logPlain(Task $task, float $now, bool $force): void
    {
        $frac = $task->fraction();
        $decile = $frac !== null ? (int) floor($frac * 10) : -1;
        $due = ($now - $this->lastLogMs) >= 2000.0
            || ($decile !== -1 && $decile !== $this->lastLogDecile);
        if (!$force && !$due) {
            return;
        }
        $this->lastLogMs = $now;
        $this->lastLogDecile = $decile;

        $parts = [];
        if ($task->status->finished()) {
            $parts[] = match ($task->status) {
                Status::Success => '[ok]',
                Status::Failure => '[fail]',
                Status::Warning => '[warn]',
                default => '[stopped]',
            };
        }
        if (($task->finalText ?? $task->label) !== '') {
            $parts[] = Text::sanitize($task->finalText ?? $task->label);
        }
        if ($frac !== null) {
            $parts[] = sprintf('%d%%', (int) floor($frac * 100));
            $parts[] = sprintf('(%d/%d)', (int) $task->completed, (int) $task->total);
        }
        foreach ($task->fields() as $k => $v) {
            $parts[] = Text::sanitize((string) $k) . '=' . Text::sanitize($v);
        }
        if ($task->startedAtMs > 0) {
            $parts[] = 'elapsed ' . Text::duration($task->elapsedSeconds($now));
        }
        $this->write(implode(' ', $parts) . "\n");
    }
}
