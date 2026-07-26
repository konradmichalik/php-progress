<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Style;

use KonradMichalik\PhpProgress\Style\Theme;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    public function testDefaultsAreUnicode(): void
    {
        $theme = new Theme();
        self::assertSame('█', $theme->doneChar);
        self::assertSame('─', $theme->trackChar);
        self::assertNotSame([], $theme->eighths);
        self::assertTrue($theme->shimmer);
        self::assertSame('✔', $theme->iconSuccess);
    }

    public function testAsciiFallback(): void
    {
        $theme = Theme::ascii();
        self::assertSame('#', $theme->doneChar);
        self::assertSame('-', $theme->trackChar);
        self::assertSame([], $theme->eighths);
        self::assertFalse($theme->shimmer);
        self::assertSame('OK', $theme->iconSuccess);
        self::assertSame('FAIL', $theme->iconFailure);
    }
}
