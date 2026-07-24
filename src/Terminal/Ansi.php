<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Terminal;

final class Ansi
{
    public const RESET = "\e[0m";
    public const BOLD = "\e[1m";
    public const DIM = "\e[2m";
    public const HIDE_CURSOR = "\e[?25l";
    public const SHOW_CURSOR = "\e[?25h";
    public const ERASE_LINE = "\r\e[2K";
    public const CR = "\r";
    public const ERASE_TO_END = "\e[0K";

    /** @return array{0:int,1:int,2:int} */
    public static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param array{0:int,1:int,2:int} $a
     * @param array{0:int,1:int,2:int} $b
     * @return array{0:int,1:int,2:int}
     */
    public static function mix(array $a, array $b, float $t): array
    {
        $t = max(0.0, min(1.0, $t));

        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public static function fg(array $rgb, string $depth): string
    {
        return match ($depth) {
            'truecolor' => sprintf("\e[38;2;%d;%d;%dm", $rgb[0], $rgb[1], $rgb[2]),
            '256' => sprintf("\e[38;5;%dm", self::to256($rgb)),
            'basic' => self::toBasic($rgb),
            default => '',
        };
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public static function to256(array $rgb): int
    {
        [$r, $g, $b] = $rgb;
        if (abs($r - $g) < 12 && abs($g - $b) < 12) {
            if ($r < 8) {
                return 16;
            }
            if ($r > 248) {
                return 231;
            }

            return 232 + (int) round(($r - 8) / 247 * 24);
        }
        $q = static fn (int $v): int => (int) round($v / 255 * 5);

        return 16 + 36 * $q($r) + 6 * $q($g) + $q($b);
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function toBasic(array $rgb): string
    {
        [$r, $g, $b] = $rgb;
        $code = 30 + (($r > 127) ? 1 : 0) + (($g > 127) ? 2 : 0) + (($b > 127) ? 4 : 0);

        return "\e[{$code}m";
    }

    /** OSC 9;4 taskbar/title progress. state: 0=clear, 1=normal, 3=indeterminate */
    public static function osc94(int $state, int $pct = 0): string
    {
        return "\e]9;4;{$state};{$pct}\x07";
    }
}
