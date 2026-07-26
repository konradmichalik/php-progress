<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Style;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Style\Spinners;
use KonradMichalik\PhpProgress\Style\Theme;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Terminal\Capabilities;
use PHPUnit\Framework\TestCase;

final class SpinnersTest extends TestCase
{
    public function testGetKnownStyle(): void
    {
        $style = Spinners::get('dots');
        self::assertSame(80, $style['interval']);
        self::assertNotSame([], $style['frames']);
    }

    public function testGetUnknownStyleFallsBackToDots(): void
    {
        self::assertSame(Spinners::get('dots'), Spinners::get('does-not-exist'));
    }

    public function testFramesAreUniformWidth(): void
    {
        $frames = Spinners::get('moon')['frames'];
        $widths = array_unique(array_map(static fn (string $f): int => Text::width($f), $frames));
        self::assertCount(1, $widths);
    }

    public function testAsciiName(): void
    {
        self::assertSame('star-ascii', Spinners::asciiName('star'));
        self::assertSame('line', Spinners::asciiName('dots')); // default fallback
    }

    public function testIsProcedural(): void
    {
        self::assertTrue(Spinners::isProcedural('wave'));
        self::assertTrue(Spinners::isProcedural('comet'));
        self::assertFalse(Spinners::isProcedural('dots'));
    }

    public function testProceduralUnknownIsNull(): void
    {
        self::assertNull(Spinners::procedural('nope'));
    }

    public function testProceduralPeriods(): void
    {
        self::assertSame(1100, Spinners::procedural('wave')['period']);
        self::assertSame(900, Spinners::procedural('comet')['period']);
    }

    public function testNormalizePadsAndFloorsInterval(): void
    {
        $norm = Spinners::normalize(['a', 'bbb'], 5);
        self::assertSame(['a  ', 'bbb'], $norm['frames']);
        self::assertSame(10, $norm['interval']); // floored to 10
    }

    public function testWaveClosureColored(): void
    {
        $fn = Spinners::procedural('wave')['fn'];
        $out = $fn(0.3, $this->frame('truecolor'));
        self::assertSame(5, Text::width(Text::stripAnsi($out)));
        self::assertStringContainsString("\e[", $out); // carries colour
    }

    public function testWaveClosureMonochrome(): void
    {
        $fn = Spinners::procedural('wave')['fn'];
        $out = $fn(0.3, $this->frame('none'));
        self::assertSame(5, Text::width($out));
        self::assertStringNotContainsString("\e[", $out);
    }

    public function testWaveClosureWithCustomColor(): void
    {
        $fn = Spinners::procedural('wave', '#7c9cff')['fn'];
        $out = $fn(0.6, $this->frame('truecolor'));
        self::assertSame(5, Text::width(Text::stripAnsi($out)));
    }

    public function testCometClosureColored(): void
    {
        $fn = Spinners::procedural('comet')['fn'];
        $out = $fn(0.5, $this->frame('truecolor'));
        self::assertSame(9, Text::width(Text::stripAnsi($out)));
    }

    public function testCometClosureMonochromeShowsShadeTrail(): void
    {
        $fn = Spinners::procedural('comet')['fn'];
        $out = $fn(0.5, $this->frame('none'));
        self::assertSame(9, Text::width($out));
        self::assertSame(1, preg_match('/[▓▒░]/u', $out));
    }

    public function testCometClosureWithCustomColor(): void
    {
        $fn = Spinners::procedural('comet', '#d97757')['fn'];
        $out = $fn(0.1, $this->frame('truecolor'));
        self::assertSame(9, Text::width(Text::stripAnsi($out)));
    }

    private function frame(string $colors): Frame
    {
        return new Frame(0.0, 72, new Capabilities(true, 72, $colors, true), new Theme());
    }
}
