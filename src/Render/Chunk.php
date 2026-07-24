<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Render;

use KonradMichalik\PhpProgress\Support\Text;

/** A rendered piece of the line: plain text for measuring, styled twin for output. */
final class Chunk
{
    public function __construct(
        public readonly string $plain,
        public readonly ?string $styled = null,
    ) {
    }

    public function width(): int
    {
        return Text::width($this->plain);
    }

    public function out(bool $colored): string
    {
        return $colored ? ($this->styled ?? $this->plain) : $this->plain;
    }
}
