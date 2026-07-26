<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Render;

use KonradMichalik\PhpProgress\Render\Chunk;
use PHPUnit\Framework\TestCase;

final class ChunkTest extends TestCase
{
    public function testWidthMeasuresPlainText(): void
    {
        self::assertSame(3, (new Chunk('abc'))->width());
        self::assertSame(2, (new Chunk('🌕'))->width());
    }

    public function testOutReturnsStyledWhenColored(): void
    {
        $chunk = new Chunk('abc', "\e[1mabc\e[0m");
        self::assertSame("\e[1mabc\e[0m", $chunk->out(true));
        self::assertSame('abc', $chunk->out(false));
    }

    public function testOutFallsBackToPlainWhenStyledNull(): void
    {
        $chunk = new Chunk('abc');
        self::assertSame('abc', $chunk->out(true));
        self::assertSame('abc', $chunk->out(false));
    }
}
