<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Process;

use KonradMichalik\PhpProgress\Live;

/**
 * Runs an external command (rsync, mysqldump | pv, ...) and feeds a live line
 * from its output. The stream_select poll loop doubles as the render tick, so
 * animations stay smooth even while the child is silent.
 */
final class Proc
{
    /** @var (callable(string): (float|null))|null */
    private $parser = null;
    /** @var (callable(Live, string, string): void)|null */
    private $onLine = null;
    private string $watch = 'stdout';
    private ?Live $live = null;
    private string $label = '';
    /** Captured output, bounded to the last CAPTURE_TAIL bytes per stream (diagnostics only). */
    public string $stdout = '';
    public string $stderr = '';

    /** Cap on retained capture per stream, and on a single un-terminated line. */
    private const CAPTURE_TAIL = 65536;
    private const MAX_LINE = 1048576;

    /**
     * @param list<string>|string $cmd Array form runs the binary directly with NO shell —
     *        use it whenever any argument is dynamic/untrusted (no injection possible).
     *        String form runs via `/bin/sh -c` for pipelines/redirection; never interpolate
     *        untrusted input into it, or you have a shell-injection hole.
     */
    public function __construct(private readonly array|string $cmd)
    {
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** Which stream carries progress info: 'stdout' | 'stderr' (pv -n uses stderr). */
    public function from(string $stream): self
    {
        $this->watch = $stream === 'stderr' ? 'stderr' : 'stdout';

        return $this;
    }

    /** Return 0-100 to drive the bar, null to just keep animating. */
    public function parse(callable $parser): self
    {
        $this->parser = $parser;

        return $this;
    }

    /** Observe every line (both streams) e.g. to ->set() fields on the live line. */
    public function onLine(callable $cb): self
    {
        $this->onLine = $cb;

        return $this;
    }

    /** Provide a pre-configured Live (custom columns, stream, theme...). */
    public function live(Live $live): self
    {
        $this->live = $live;

        return $this;
    }

    public function run(): int
    {
        $live = $this->live ?? ($this->parser !== null
            ? Live::bar(100.0, $this->label)->columns('label', 'bar', 'percent', 'fields', 'elapsed')
            : Live::spinner($this->label));
        $live->start();

        $spec = [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($this->cmd, $spec, $pipes);
        if (!\is_resource($proc)) {
            $live->fail('failed to start process');

            return 1;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $partial = ['stdout' => '', 'stderr' => ''];
        $exitCode = null;

        while (true) {
            $read = [];
            if (!feof($pipes[1])) {
                $read[] = $pipes[1];
            }
            if (!feof($pipes[2])) {
                $read[] = $pipes[2];
            }
            if ($read === []) {
                usleep(20000);
            } else {
                $w = null;
                $e = null;
                @stream_select($read, $w, $e, 0, 80000);
                foreach ($read as $r) {
                    $name = $r === $pipes[1] ? 'stdout' : 'stderr';
                    $data = (string) fread($r, 65536);
                    if ($data === '') {
                        continue;
                    }
                    $this->{$name} .= $data;
                    if (\strlen($this->{$name}) > self::CAPTURE_TAIL) {
                        // Keep only the tail: enough to diagnose a failure, bounded memory.
                        $this->{$name} = substr($this->{$name}, -self::CAPTURE_TAIL);
                    }
                    $partial[$name] .= $data;
                    // rsync & friends update in place via \r -- treat \r like \n.
                    $lines = preg_split('/\r\n|\r|\n/', $partial[$name]) ?: [];
                    $partial[$name] = array_pop($lines) ?? '';
                    // A process emitting no line terminator must not grow $partial without bound.
                    if (\strlen($partial[$name]) > self::MAX_LINE) {
                        $this->handleLine($live, $name, substr($partial[$name], 0, self::MAX_LINE));
                        $partial[$name] = '';
                    }
                    foreach ($lines as $line) {
                        $this->handleLine($live, $name, $line);
                    }
                }
            }

            $live->tick();

            $status = proc_get_status($proc);
            if (!$status['running']) {
                $exitCode ??= $status['exitcode'];
                if ($read === []) {
                    break;
                }
            }
        }

        foreach (['stdout', 'stderr'] as $name) {
            if ($partial[$name] !== '') {
                $this->handleLine($live, $name, $partial[$name]);
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        // $exitCode is guaranteed set: the loop only breaks after reading a finished status.
        if ($exitCode === 0) {
            $live->finish();
        } else {
            $live->fail(($this->label !== '' ? $this->label . ' ' : '') . "failed (exit {$exitCode})");
        }

        return (int) $exitCode;
    }

    private function handleLine(Live $live, string $stream, string $line): void
    {
        if ($line === '') {
            return;
        }
        if ($this->onLine !== null) {
            ($this->onLine)($live, $stream, $line);
        }
        if ($this->parser !== null && $stream === $this->watch) {
            $pct = ($this->parser)($line);
            if ($pct !== null) {
                $live->progress(max(0.0, min(100.0, (float) $pct)));
            }
        }
    }
}
