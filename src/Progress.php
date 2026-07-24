<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress;

use KonradMichalik\PhpProgress\Process\Proc;

/** Facade: the four entry points. */
final class Progress
{
    public static function bar(?float $total = null, string $label = ''): Live
    {
        return Live::bar($total, $label);
    }

    public static function spinner(string $label = ''): Live
    {
        return Live::spinner($label);
    }

    /**
     * tqdm-style iteration wrapper: counts, renders, finishes -- zero ceremony.
     *
     * @template TKey
     * @template TValue
     * @param iterable<TKey, TValue> $items
     * @return \Generator<TKey, TValue>
     */
    public static function track(iterable $items, string $label = '', ?int $total = null, ?Live $live = null): \Generator
    {
        if ($total === null && is_countable($items)) {
            $total = \count($items);
        }
        $live ??= Live::bar($total !== null ? (float) $total : null, $label);
        if ($total !== null) {
            $live->total((float) $total);
        }
        $live->start();
        try {
            foreach ($items as $key => $value) {
                yield $key => $value;
                $live->advance();
            }
            $live->finish();
        } catch (\Throwable $e) {
            $live->fail();
            throw $e;
        } finally {
            // Runs on generator destruction too -- breaking out of the loop
            // must not leave a live line (and hidden cursor) behind.
            if (!$live->task()->status->finished()) {
                $live->stop();
            }
        }
    }

    /** @param list<string>|string $cmd */
    public static function process(array|string $cmd): Proc
    {
        return new Proc($cmd);
    }
}
