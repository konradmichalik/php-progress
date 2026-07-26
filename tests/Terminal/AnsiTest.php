<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Terminal;

use KonradMichalik\PhpProgress\Terminal\Ansi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnsiTest extends TestCase
{
    public function testHexParsesRgb(): void
    {
        self::assertSame([255, 255, 255], Ansi::hex('#ffffff'));
        self::assertSame([1, 2, 3], Ansi::hex('010203'));
        self::assertSame([217, 119, 87], Ansi::hex('#d97757'));
    }

    public function testMixInterpolates(): void
    {
        self::assertSame([128, 128, 128], Ansi::mix([0, 0, 0], [255, 255, 255], 0.5));
    }

    public function testMixClampsFactor(): void
    {
        self::assertSame([0, 0, 0], Ansi::mix([0, 0, 0], [255, 255, 255], -1.0));
        self::assertSame([255, 255, 255], Ansi::mix([0, 0, 0], [255, 255, 255], 2.0));
    }

    public function testFgTruecolor(): void
    {
        self::assertSame("\e[38;2;1;2;3m", Ansi::fg([1, 2, 3], 'truecolor'));
    }

    public function testFg256(): void
    {
        self::assertSame("\e[38;5;196m", Ansi::fg([255, 0, 0], '256'));
    }

    public function testFgBasic(): void
    {
        self::assertSame("\e[31m", Ansi::fg([200, 0, 0], 'basic'));
    }

    public function testFgNoneIsEmpty(): void
    {
        self::assertSame('', Ansi::fg([1, 2, 3], 'none'));
    }

    #[DataProvider('to256Provider')]
    public function testTo256(array $rgb, int $expected): void
    {
        self::assertSame($expected, Ansi::to256($rgb));
    }

    /** @return list<array{array{0:int,1:int,2:int}, int}> */
    public static function to256Provider(): array
    {
        return [
            'near-black gray' => [[0, 0, 0], 16],
            'near-white gray' => [[255, 255, 255], 231],
            'mid gray' => [[128, 128, 128], 244],
            'pure red' => [[255, 0, 0], 196],
        ];
    }

    public function testOsc94(): void
    {
        self::assertSame("\e]9;4;1;50\x07", Ansi::osc94(1, 50));
        self::assertSame("\e]9;4;0;0\x07", Ansi::osc94(0));
        self::assertSame("\e]9;4;3;0\x07", Ansi::osc94(3));
    }
}
