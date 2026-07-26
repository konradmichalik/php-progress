<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Terminal\Capabilities;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Shared helpers: an in-memory output stream, frame extraction from the raw
 * repaint bytes, and a simulated-terminal Capabilities factory. These keep the
 * suite deterministic — no real TTY, wall clock or environment involved.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * An in-memory stream plus a reader that returns everything written so far.
     *
     * @return array{0: resource, 1: callable(): string}
     */
    protected function memStream(): array
    {
        $stream = fopen('php://temp', 'w+');
        \assert(\is_resource($stream));

        return [$stream, static function () use ($stream): string {
            rewind($stream);

            return stream_get_contents($stream) ?: '';
        }];
    }

    /**
     * Split raw output into the visible (ANSI-stripped) frames. Live repaints
     * start with a bare \r; println()/finish() use \r\e[2K — normalize both to \r.
     *
     * @return list<string>
     */
    protected function frames(string $raw): array
    {
        $normalized = str_replace("\r\e[2K", "\r", $raw);
        $parts = explode("\r", $normalized);
        array_shift($parts); // cursor-hide etc. before the first carriage return

        $out = [];
        foreach ($parts as $part) {
            $out[] = rtrim(Text::stripAnsi($part), "\n");
        }

        return $out;
    }

    /** A simulated terminal. */
    protected function tty(int $width, string $colors = 'truecolor', bool $unicode = true): Capabilities
    {
        return new Capabilities(true, $width, $colors, $unicode);
    }

    /** A per-render context for exercising segments directly. */
    protected function frame(
        int $width = 80,
        string $colors = 'truecolor',
        bool $unicode = true,
        float $nowMs = 0.0,
        ?Theme $theme = null,
    ): Frame {
        return new Frame(
            $nowMs,
            $width,
            new Capabilities(true, $width, $colors, $unicode),
            $theme ?? ($unicode ? new Theme() : Theme::ascii()),
        );
    }
}
