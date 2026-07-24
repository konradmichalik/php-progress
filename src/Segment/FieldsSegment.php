<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Segment;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Render\Chunk;
use KonradMichalik\PhpProgress\Render\Segment;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;
use KonradMichalik\PhpProgress\Terminal\Ansi;

/**
 * Runtime key/value extras. set()/clear() make them appear and vanish.
 * Within its width budget, sticky pairs are packed first; non-sticky pairs
 * only join while space remains -- so squeezing never mangles a sticky value.
 * Under line-level pressure, Layout degrades this segment to sticky-only.
 */
final class FieldsSegment implements Segment
{
    private const VALUE_MAX = 28;
    private const SEP = ' · ';

    public function key(): string
    {
        return 'fields';
    }

    public function priority(): int
    {
        return 60;
    }

    public function canDegrade(int $level): bool
    {
        return $level < 1;
    }

    public function render(Task $task, Frame $frame, int $level): ?Chunk
    {
        $sticky = $task->fields(onlySticky: true);
        $extra = $level >= 1 ? [] : array_diff_key($task->fields(), $sticky);
        if ($sticky === [] && $extra === []) {
            return null;
        }

        $cap = max(16, intdiv($frame->width, 3));
        $sepW = Text::width(self::SEP);
        $plainParts = [];
        $styledParts = [];
        $used = 0;

        foreach ([$sticky, $extra] as $group) {
            foreach ($group as $key => $value) {
                $key = Text::sanitize((string) $key);
                $value = Text::truncateMiddle(Text::sanitize($value), self::VALUE_MAX);
                $pairW = Text::width($key) + 1 + Text::width($value);
                $need = ($plainParts === [] ? 0 : $sepW) + $pairW;

                if ($used + $need > $cap) {
                    // A lone over-long pair squeezes its value; later pairs just wait
                    // for a wider terminal instead of mangling everything.
                    if ($plainParts !== []) {
                        continue 2;
                    }
                    $room = $cap - Text::width($key) - 1;
                    if ($room < 4) {
                        continue 2;
                    }
                    $value = Text::truncateMiddle($value, $room);
                    $need = Text::width($key) + 1 + Text::width($value);
                }

                $plainParts[] = $key . ' ' . $value;
                $styledParts[] = Ansi::DIM . $key . Ansi::RESET . ' ' . $value;
                $used += $need;
            }
        }

        if ($plainParts === []) {
            return null;
        }

        return new Chunk(
            implode(self::SEP, $plainParts),
            $frame->colored() ? implode(Ansi::DIM . self::SEP . Ansi::RESET, $styledParts) : null,
        );
    }
}
