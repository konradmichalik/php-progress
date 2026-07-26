<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The SIGINT/SIGTERM handling cannot be exercised in-process (the handler
 * re-raises the signal to preserve the 128+signo exit code, which would kill the
 * test runner). It is validated end-to-end in a real child process instead.
 */
final class SignalTest extends TestCase
{
    public function testSigintRestoresCursorAndReRaises(): void
    {
        if (!\function_exists('pcntl_signal') || stripos(PHP_OS, 'WIN') === 0) {
            self::markTestSkipped('pcntl unavailable or Windows');
        }

        $out = tempnam(sys_get_temp_dir(), 'phpprogress_sig_out_');
        $ready = tempnam(sys_get_temp_dir(), 'phpprogress_sig_rdy_');
        \assert(\is_string($out) && \is_string($ready));
        @unlink($ready);
        $worker = __DIR__ . '/signal_worker.php';

        // Array form runs php directly (no /bin/sh wrapper), so the pid is the PHP
        // process and a plain SIGINT reaches php-progress's handler.
        $proc = proc_open(
            ['php', $worker, $out, $ready],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($proc);
        $pid = proc_get_status($proc)['pid'];

        for ($i = 0; $i < 200 && !is_file($ready); $i++) {
            usleep(10000);
        }
        usleep(100000);

        if (\function_exists('posix_kill')) {
            @posix_kill($pid, SIGINT);
        } else {
            exec('kill -INT ' . (int) $pid . ' 2>/dev/null'); // @codeCoverageIgnore
        }

        $deadline = microtime(true) + 3.0;
        do {
            $st = proc_get_status($proc);
            usleep(20000);
        } while ($st['running'] && microtime(true) < $deadline);

        if ($st['running'] && \function_exists('posix_kill')) {
            @posix_kill($pid, SIGKILL); // @codeCoverageIgnore
            usleep(50000);              // @codeCoverageIgnore
        }

        $signaled = $st['signaled'];
        $termsig = $st['termsig'];
        @fclose($pipes[1]);
        @fclose($pipes[2]);
        @proc_close($proc);

        $raw = (string) @file_get_contents($out);
        @unlink($out);
        @unlink($ready);

        self::assertTrue($signaled, 'worker terminated by a signal');
        self::assertSame(SIGINT, $termsig, 'terminated by re-raised SIGINT');
        self::assertStringContainsString("\e[?25h", $raw, 'cursor restored');
        self::assertStringContainsString("\e]9;4;0;0\x07", $raw, 'taskbar progress cleared');
        self::assertStringContainsString("\e[?25h", substr($raw, -12), 'restore is at the very end');
    }
}
