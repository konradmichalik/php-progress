<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Support;

use KonradMichalik\PhpProgress\Support\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testWidthCountsDisplayColumns(): void
    {
        self::assertSame(5, Text::width('hello'));
        self::assertSame(2, Text::width('🌕')); // wide glyph
        self::assertSame(0, Text::width(''));
    }

    public function testPadRightIsDefault(): void
    {
        self::assertSame('ab   ', Text::pad('ab', 5));
    }

    public function testPadLeft(): void
    {
        self::assertSame('   ab', Text::pad('ab', 5, STR_PAD_LEFT));
    }

    public function testPadLeavesLongerStringUntouched(): void
    {
        self::assertSame('abcdef', Text::pad('abcdef', 3));
    }

    public function testTruncateMiddleKeepsShortStrings(): void
    {
        self::assertSame('short', Text::truncateMiddle('short', 20));
    }

    public function testTruncateMiddleWithTinyBudget(): void
    {
        self::assertSame('…', Text::truncateMiddle('anything long', 1));
    }

    public function testTruncateMiddleIsPathAware(): void
    {
        $out = Text::truncateMiddle('/var/www/project/deploy/dump.sql.gz', 20);
        self::assertLessThanOrEqual(20, Text::width($out));
        self::assertStringContainsString('…', $out);
        self::assertStringStartsWith('/var', $out);
        self::assertStringEndsWith('.gz', $out);
    }

    public function testTruncateEndKeepsShortStrings(): void
    {
        self::assertSame('short', Text::truncateEnd('short', 20));
    }

    public function testTruncateEndWithTinyBudget(): void
    {
        self::assertSame('…', Text::truncateEnd('anything long', 1));
    }

    public function testTruncateEndAppendsEllipsis(): void
    {
        $out = Text::truncateEnd('a rather long label here', 10);
        self::assertLessThanOrEqual(10, Text::width($out));
        self::assertStringEndsWith('…', $out);
        self::assertStringStartsWith('a rather', $out);
    }

    #[DataProvider('bytesProvider')]
    public function testBytesHumanize(float $n, string $expected): void
    {
        self::assertSame($expected, Text::bytes($n));
    }

    /** @return list<array{float, string}> */
    public static function bytesProvider(): array
    {
        return [
            [512.0, '512 B'],
            [1536.0, '1.5 KB'],
            [1024.0 * 1024 * 1.5, '1.5 MB'],
            [1024.0 ** 3, '1.0 GB'],
            [1024.0 ** 4, '1.0 TB'],
            [1024.0 ** 5, '1024.0 TB'], // caps at TB (i < 4)
        ];
    }

    #[DataProvider('durationProvider')]
    public function testDuration(float $seconds, string $expected): void
    {
        self::assertSame($expected, Text::duration($seconds));
    }

    /** @return list<array{float, string}> */
    public static function durationProvider(): array
    {
        return [
            [0.0, '0:00'],
            [59.4, '0:59'],
            [3723.0, '1:02:03'],
        ];
    }

    public function testStripAnsiRemovesCsiAndOsc(): void
    {
        self::assertSame('ABC', Text::stripAnsi("\e[38;2;1;2;3mABC\e[0m"));
        self::assertSame('xy', Text::stripAnsi("x\e]9;4;1;50\x07y"));
    }

    public function testClampAnsiZeroWidth(): void
    {
        self::assertSame('', Text::clampAnsi("\e[1mABC\e[0m", 0));
    }

    public function testClampAnsiLeavesFittingLineUntouched(): void
    {
        $styled = "\e[38;2;1;2;3mABCDEFG\e[0m";
        self::assertSame($styled, Text::clampAnsi($styled, 99));
    }

    public function testClampAnsiCutsAndClosesColour(): void
    {
        $styled = "\e[38;2;1;2;3mABCDEFG\e[0m";
        $clamped = Text::clampAnsi($styled, 3);
        self::assertSame(3, Text::width(Text::stripAnsi($clamped)));
        self::assertStringEndsWith("\e[0m", $clamped);
    }

    public function testClampAnsiPlainTextNeedsNoReset(): void
    {
        self::assertSame('ABC', Text::clampAnsi('ABCDEFG', 3));
    }

    public function testClampAnsiKeepsLeadingOscVerbatim(): void
    {
        $clamped = Text::clampAnsi("\e]9;4;1;50\x07ABCDEFG", 3);
        self::assertStringContainsString("\e]9;4;1;50\x07", $clamped);
        self::assertSame(3, Text::width(Text::stripAnsi($clamped)));
    }

    public function testSanitizeEmptyStringShortCircuits(): void
    {
        self::assertSame('', Text::sanitize(''));
    }

    public function testSanitizeLeavesPrintableIntact(): void
    {
        self::assertSame('plain/path.mp4', Text::sanitize('plain/path.mp4'));
    }

    public function testSanitizeStripsC0Controls(): void
    {
        self::assertSame('ab', Text::sanitize("a\r\rb"));
        self::assertSame('tabnldel', Text::sanitize("tab\tnl\ndel\x7f"));
    }

    public function testSanitizeStripsSevenBitSequences(): void
    {
        self::assertSame('xy', Text::sanitize("x\e]0;title\x07y"));       // OSC
        self::assertSame('xy', Text::sanitize("x\e[2J\e[1;1Hy"));          // CSI
        self::assertSame('ab', Text::sanitize("a\eP1;2pfoo\e\\b"));        // DCS
        self::assertSame('ab', Text::sanitize("a\e_apc\e\\b"));            // APC
        self::assertSame('ab', Text::sanitize("a\e^pm\e\\b"));             // PM
    }

    public function testSanitizeStripsEightBitC1Sequences(): void
    {
        self::assertSame('', Text::sanitize("\u{009b}2J"));                       // 8-bit CSI
        self::assertSame('', Text::sanitize("\u{009d}0;PWNED\u{009c}"));          // 8-bit OSC
        self::assertSame('', Text::sanitize("\u{0090}dcs\u{009c}"));              // 8-bit DCS
        self::assertSame(0, preg_match('/[\x{0080}-\x{009F}]/u', Text::sanitize("a\u{0090}\u{009e}\u{009f}b")));
    }

    public function testSanitizeYieldsValidUtf8FromGarbage(): void
    {
        self::assertTrue(mb_check_encoding(Text::sanitize("bad\xFF\xFEbytes"), 'UTF-8'));
    }
}
