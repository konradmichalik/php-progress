<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Render;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Task;

/**
 * Responsive single-line reflow:
 *  1. render fixed segments, measure plain widths
 *  2. give the flex segment (bar) the remainder
 *  3. under pressure: degrade (label shrinks, fields -> sticky-only),
 *     then drop lowest-priority segments; essentials (>= 95) never drop.
 */
final class Layout
{
    private const SEP = '  ';

    /** @param list<Segment> $segments */
    public function __construct(
        private readonly array $segments,
        private readonly int $flexMax = 40,
        private readonly bool $expand = false,
    ) {
    }

    public function compose(Task $task, Frame $frame): string
    {
        $sepW = Text::width(self::SEP);
        $levels = [];
        $dropped = [];
        $flex = null;
        foreach ($this->segments as $seg) {
            if ($seg instanceof FlexSegment) {
                $flex = $seg;
            }
            $levels[$seg->key()] = 0;
        }

        $chunks = [];
        $flexAvail = 0;

        for ($guard = 0; $guard < 32; $guard++) {
            $chunks = [];
            $fixedW = 0;
            foreach ($this->segments as $seg) {
                if ($seg instanceof FlexSegment || isset($dropped[$seg->key()])) {
                    continue;
                }
                $chunk = $seg->render($task, $frame, $levels[$seg->key()]);
                if ($chunk === null) {
                    continue;
                }
                $chunks[$seg->key()] = $chunk;
                $fixedW += $chunk->width();
            }

            $parts = \count($chunks) + ($flex !== null ? 1 : 0);
            $gaps = max(0, $parts - 1);
            $flexAvail = $frame->width - $fixedW - $gaps * $sepW;

            $fits = $flex !== null ? $flexAvail >= $flex->minWidth() : $flexAvail >= 0;
            if ($fits) {
                break;
            }

            if (!$this->tighten($task, $chunks, $levels, $dropped)) {
                break; // nothing left to sacrifice; render at bar minimum
            }
        }

        // Assemble in declared segment order, tracking the visible (plain) width
        // so we can enforce the terminal-width invariant as a last resort.
        $out = [];
        $visible = 0;
        foreach ($this->segments as $seg) {
            $key = $seg->key();
            if ($seg instanceof FlexSegment) {
                $barW = $this->expand ? $flexAvail : min($flexAvail, $this->flexMax);
                $barW = max(3, $barW);
                $out[] = $seg->renderFlex($task, $frame, $barW)->out($frame->colored());
                $visible += $barW;
                continue;
            }
            if (isset($chunks[$key])) {
                $out[] = $chunks[$key]->out($frame->colored());
                $visible += $chunks[$key]->width();
            }
        }
        $visible += max(0, \count($out) - 1) * $sepW;

        $line = implode(self::SEP, $out);

        // When even the undroppable columns (bar minimum, percent, sticky fields)
        // exceed the terminal, hard-truncate so the line never wraps and corrupts
        // the CR-based repaint. Normal-width lines pass through untouched.
        return $visible > $frame->width ? Text::clampAnsi($line, $frame->width) : $line;
    }

    /**
     * @param array<string, Chunk> $chunks
     * @param array<string, int> $levels
     * @param array<string, true> $dropped
     */
    private function tighten(Task $task, array $chunks, array &$levels, array &$dropped): bool
    {
        $candidates = [];
        foreach ($this->segments as $seg) {
            $key = $seg->key();
            if ($seg instanceof FlexSegment || isset($dropped[$key]) || !isset($chunks[$key])) {
                continue;
            }
            $candidates[] = $seg;
        }
        usort($candidates, static fn (Segment $a, Segment $b): int => $a->priority() <=> $b->priority());

        // Act on the LOWEST-priority segment first; per segment prefer
        // degrading over dropping. This guarantees e.g. rate/eta vanish
        // before the fields segment loses anything.
        foreach ($candidates as $seg) {
            $key = $seg->key();
            if ($seg->canDegrade($levels[$key])) {
                $levels[$key]++;

                return true;
            }
            if ($seg->priority() >= 95) {
                continue;
            }
            // Sticky fields are the user's promise -- never drop them entirely.
            if ($key === 'fields' && $task->hasStickyFields()) {
                continue;
            }
            $dropped[$key] = true;

            return true;
        }

        return false;
    }
}
