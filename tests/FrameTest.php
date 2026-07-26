<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Terminal\Capabilities;
use PHPUnit\Framework\TestCase;

final class FrameTest extends TestCase
{
    public function testColoredWhenColorDepthPresent(): void
    {
        $frame = new Frame(0.0, 80, new Capabilities(true, 80, 'truecolor', true), new Theme());
        self::assertTrue($frame->colored());
    }

    public function testNotColoredWhenDepthNone(): void
    {
        $frame = new Frame(0.0, 80, new Capabilities(true, 80, 'none', true), new Theme());
        self::assertFalse($frame->colored());
    }
}
