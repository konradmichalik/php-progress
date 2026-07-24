<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Style;

final class Theme
{
    /** @param list<string> $eighths */
    public function __construct(
        public string $gradientFrom = '#22d3ee',
        public string $gradientTo = '#818cf8',
        public string $accent = '#818cf8',
        public string $good = '#34d399',
        public string $bad = '#f87171',
        public string $warn = '#fbbf24',
        public string $muted = '#585b70',
        public string $doneChar = '█',
        public string $trackChar = '─',
        public array $eighths = ['▏', '▎', '▍', '▌', '▋', '▊', '▉'],
        public bool $shimmer = true,
        public string $iconSuccess = '✔',
        public string $iconFailure = '✖',
        public string $iconWarning = '⚠',
        public string $iconStopped = '■',
    ) {
    }

    /** Fallback for terminals without UTF-8 locale. */
    public static function ascii(): self
    {
        return new self(
            doneChar: '#',
            trackChar: '-',
            eighths: [],
            shimmer: false,
            iconSuccess: 'OK',
            iconFailure: 'FAIL',
            iconWarning: '!',
            iconStopped: 'x',
        );
    }
}
