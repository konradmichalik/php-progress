<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Style;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Terminal\Ansi;

/**
 * Spinner catalog, cli-spinners compatible: any custom style is just frames + interval.
 * Frames are padded to uniform display width so the line never jitters horizontally.
 */
final class Spinners
{
    /** @var array<string, array{frames: list<string>, interval: int}> */
    private const STYLES = [
        'dots' => ['frames' => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'], 'interval' => 80],
        'dots2' => ['frames' => ['⣾', '⣽', '⣻', '⢿', '⡿', '⣟', '⣯', '⣷'], 'interval' => 80],
        'line' => ['frames' => ['-', '\\', '|', '/'], 'interval' => 110],
        'arc' => ['frames' => ['◜', '◠', '◝', '◞', '◡', '◟'], 'interval' => 100],
        'circleHalves' => ['frames' => ['◐', '◓', '◑', '◒'], 'interval' => 120],
        'moon' => ['frames' => ['🌑', '🌒', '🌓', '🌔', '🌕', '🌖', '🌗', '🌘'], 'interval' => 100],
        'point' => ['frames' => ['∙∙∙', '●∙∙', '∙●∙', '∙∙●', '∙∙∙'], 'interval' => 140],
        // Claude Code-style twinkle: a single mark pulsing dot -> sparkle -> full
        // star -> back, cycling through asterisk dingbats of increasing weight.
        // Pair with ->color('#d97757') for the terracotta brand look.
        'star' => ['frames' => ['·', '✢', '✳', '✶', '✻', '✽', '✻', '✶', '✳', '✢'], 'interval' => 90],
        // ASCII fallback (mirrors Claude Code's `*`-centered non-Darwin behaviour).
        'star-ascii' => ['frames' => ['.', '+', '*', '+'], 'interval' => 100],
    ];

    /** Style to use on terminals without a UTF-8 locale. */
    private const ASCII_FALLBACK = [
        'star' => 'star-ascii',
    ];

    /** @return array{frames: list<string>, interval: int} */
    public static function get(string $name): array
    {
        $style = self::STYLES[$name] ?? self::STYLES['dots'];

        return self::normalize($style['frames'], $style['interval']);
    }

    /** Resolve the style name to use on a non-UTF-8 terminal. */
    public static function asciiName(string $name): string
    {
        return self::ASCII_FALLBACK[$name] ?? 'line';
    }

    /** Procedural styles are computed per frame from a wall-clock phase, not enumerated. */
    public static function isProcedural(string $name): bool
    {
        return \in_array($name, ['wave', 'comet'], true);
    }

    /**
     * Build a procedural spinner: a phase-driven closure plus its cycle period.
     * The optional $colorHex overrides the theme's base colour.
     *
     * @return array{fn: \Closure(float, Frame): string, period: int}|null
     */
    public static function procedural(string $name, ?string $colorHex = null): ?array
    {
        return match ($name) {
            'wave' => ['fn' => self::wave($colorHex), 'period' => 1100],
            'comet' => ['fn' => self::comet($colorHex), 'period' => 900],
            default => null,
        };
    }

    /**
     * Travelling equalizer: N cells of block glyphs whose heights follow a
     * sine wave, phase-shifted per cell so the crest scrolls across.
     *
     * @return \Closure(float, Frame): string
     */
    private static function wave(?string $colorHex): \Closure
    {
        $cells = 5;
        $blocks = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
        $top = \count($blocks) - 1;

        return static function (float $phase, Frame $frame) use ($cells, $blocks, $top, $colorHex): string {
            $depth = $frame->caps->colors;
            $colored = $frame->colored();
            $from = Ansi::hex($frame->theme->gradientFrom);
            $to = Ansi::hex($frame->theme->gradientTo);
            $base = $colorHex !== null ? Ansi::hex($colorHex) : null;

            $out = '';
            for ($i = 0; $i < $cells; $i++) {
                $v = sin(2 * M_PI * ($phase + $i / $cells));       // -1..1
                $level = (int) round(($v + 1) / 2 * $top);          // 0..top
                $ch = $blocks[$level];
                if ($colored) {
                    $rgb = $base ?? Ansi::mix($from, $to, $i / ($cells - 1));
                    $out .= Ansi::fg($rgb, $depth) . $ch;
                } else {
                    $out .= $ch;
                }
            }

            return $colored ? $out . Ansi::RESET : $out;
        };
    }

    /**
     * A head that flies across a fixed track leaving a fading shade trail.
     * Built from the block-shade family so the tail fades even without colour.
     *
     * @return \Closure(float, Frame): string
     */
    private static function comet(?string $colorHex): \Closure
    {
        $width = 9;
        $trail = ['█', '▓', '▒', '░'];   // by distance behind the head
        $track = '·';

        return static function (float $phase, Frame $frame) use ($width, $trail, $track, $colorHex): string {
            $depth = $frame->caps->colors;
            $colored = $frame->colored();
            $accent = $colorHex !== null ? Ansi::hex($colorHex) : Ansi::hex($frame->theme->accent);
            $muted = Ansi::hex($frame->theme->muted);
            $head = $phase * $width;                                 // 0..width

            $out = '';
            for ($i = 0; $i < $width; $i++) {
                $d = (int) round(fmod($head - $i + $width, (float) $width));
                if ($d < \count($trail)) {
                    $ch = $trail[$d];
                    $b = 1.0 - $d / \count($trail);                  // brightness 1..~0
                    $out .= $colored ? Ansi::fg(Ansi::mix($muted, $accent, $b), $depth) . $ch : $ch;
                } else {
                    $out .= $colored ? Ansi::fg($muted, $depth) . $track : $track;
                }
            }

            return $colored ? $out . Ansi::RESET : $out;
        };
    }

    /**
     * @param list<string> $frames
     * @return array{frames: list<string>, interval: int}
     */
    public static function normalize(array $frames, int $intervalMs): array
    {
        $max = 0;
        foreach ($frames as $f) {
            $max = max($max, Text::width($f));
        }
        $padded = array_map(static fn (string $f): string => Text::pad($f, $max), $frames);

        return ['frames' => array_values($padded), 'interval' => max(10, $intervalMs)];
    }
}
