<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'KonradMichalik\\PhpProgress\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "  ok  {$msg}\n";
}

/** @return array{0: resource, 1: callable(): string} */
function memStream(): array
{
    $s = fopen('php://temp', 'w+');
    return [$s, static function () use ($s): string {
        rewind($s);
        return stream_get_contents($s) ?: '';
    }];
}

/** @return list<string> stripped visible frames (each live repaint starts with \r) */
function frames(string $raw): array
{
    // Live repaints now start with a bare \r (flicker-free path); println/finish
    // still emit \r\e[2K. Normalize the erase form to \r, then split on \r.
    $normalized = str_replace("\r\e[2K", "\r", $raw);
    $parts = explode("\r", $normalized);
    array_shift($parts); // text before the first carriage return (cursor hide etc.)

    $out = [];
    foreach ($parts as $p) {
        // A frame may carry a trailing erase-to-end and OSC sequence; strip all ANSI.
        $clean = \KonradMichalik\PhpProgress\Support\Text::stripAnsi($p);
        // Drop the newline that finish()/println() append so widths stay exact.
        $out[] = rtrim($clean, "\n");
    }

    return $out;
}
