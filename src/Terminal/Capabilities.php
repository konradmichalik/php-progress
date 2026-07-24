<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Terminal;

final class Capabilities
{
    public function __construct(
        public readonly bool $tty,
        public readonly int $width,
        public readonly string $colors,   // 'truecolor' | '256' | 'basic' | 'none'
        public readonly bool $unicode,
    ) {
    }

    /** @param resource $stream */
    public static function detect($stream): self
    {
        $tty = @stream_isatty($stream);
        $width = self::detectWidth($tty);

        $colors = 'none';
        if ($tty && getenv('NO_COLOR') === false) {
            $ct = strtolower((string) getenv('COLORTERM'));
            $term = strtolower((string) getenv('TERM'));
            if (str_contains($ct, 'truecolor') || str_contains($ct, '24bit')) {
                $colors = 'truecolor';
            } elseif (str_contains($term, '256color')) {
                $colors = '256';
            } elseif ($term !== '' && $term !== 'dumb') {
                $colors = 'basic';
            }
        }

        $lang = strtoupper((string) (getenv('LC_ALL') ?: getenv('LC_CTYPE') ?: getenv('LANG')));
        $unicode = str_contains($lang, 'UTF-8') || str_contains($lang, 'UTF8');

        return new self($tty, $width, $colors, $unicode);
    }

    /** Live width lookup -- also used by the renderer to follow terminal resizes. */
    public static function detectWidth(bool $tty): int
    {
        $width = (int) (getenv('COLUMNS') ?: 0);
        if ($width <= 0 && $tty && \function_exists('exec')) {
            $out = @exec('stty size 2>/dev/null');
            if (\is_string($out) && preg_match('/^\d+\s+(\d+)$/', trim($out), $m)) {
                $width = (int) $m[1];
            }
        }

        return $width > 0 ? $width : 80;
    }
}
