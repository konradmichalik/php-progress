<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Support;

final class Text
{
    public static function width(string $s): int
    {
        return mb_strwidth($s, 'UTF-8');
    }

    public static function pad(string $s, int $width, int $type = STR_PAD_RIGHT): string
    {
        $diff = $width - self::width($s);
        if ($diff <= 0) {
            return $s;
        }
        $pad = str_repeat(' ', $diff);

        return $type === STR_PAD_LEFT ? $pad . $s : $s . $pad;
    }

    /** Middle-ellipsis, display-width aware (keeps start & end, e.g. paths). */
    public static function truncateMiddle(string $s, int $max): string
    {
        if (self::width($s) <= $max) {
            return $s;
        }
        if ($max <= 1) {
            return '…';
        }
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keep = $max - 1;
        $leftBudget = intdiv($keep, 2);

        $left = '';
        $w = 0;
        $i = 0;
        $n = \count($chars);
        while ($i < $n && $w + mb_strwidth($chars[$i], 'UTF-8') <= $leftBudget) {
            $left .= $chars[$i];
            $w += mb_strwidth($chars[$i], 'UTF-8');
            $i++;
        }
        $rightBudget = $keep - $w;
        $right = '';
        $w2 = 0;
        $j = $n - 1;
        while ($j >= $i && $w2 + mb_strwidth($chars[$j], 'UTF-8') <= $rightBudget) {
            $right = $chars[$j] . $right;
            $w2 += mb_strwidth($chars[$j], 'UTF-8');
            $j--;
        }

        return $left . '…' . $right;
    }

    public static function truncateEnd(string $s, int $max): string
    {
        if (self::width($s) <= $max) {
            return $s;
        }
        if ($max <= 1) {
            return '…';
        }
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        $w = 0;
        foreach ($chars as $ch) {
            $cw = mb_strwidth($ch, 'UTF-8');
            if ($w + $cw > $max - 1) {
                break;
            }
            $out .= $ch;
            $w += $cw;
        }

        return $out . '…';
    }

    public static function bytes(float $n): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($n >= 1024 && $i < 4) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? sprintf('%d', $n) : sprintf('%.1f', $n)) . ' ' . $units[$i];
    }

    public static function duration(float $seconds): string
    {
        $seconds = (int) round($seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    public static function stripAnsi(string $s): string
    {
        return preg_replace('/\e\][^\a\e]*(?:\a|\e\\\\)|\e\[[0-9;?]*[A-Za-z]/u', '', $s) ?? $s;
    }

    /**
     * Neutralize untrusted display text before it reaches the terminal.
     * Scrubs invalid UTF-8, strips escape sequences (OSC/DCS/CSI), and removes
     * every remaining C0 control byte and DEL. Without this, a value such as a
     * filename or a subprocess log line could carry `\r`, `\e]0;…\a` or `\e[2J`
     * and corrupt the line or drive the terminal (title changes, screen clears).
     */
    public static function sanitize(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        // Drop malformed bytes so later width math and /u regexes stay valid.
        $s = (string) mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        // Remove escape-introduced sequences so their printable payload does not linger.
        $s = preg_replace('/\e\][^\x07\e]*(?:\x07|\e\\\\)/', '', $s) ?? $s;   // OSC
        $s = preg_replace('/\e[P^_X][^\e]*(?:\e\\\\)?/', '', $s) ?? $s;       // DCS/APC/PM/SOS
        $s = preg_replace('/\e\[[0-9;?]*[ -\/]*[@-~]/', '', $s) ?? $s;        // CSI
        // Hard guarantee: no control byte (incl. lone ESC, CR, LF, TAB, BEL, BS, DEL) survives.
        return preg_replace('/[\x00-\x1F\x7F]/', '', $s) ?? $s;
    }
}
