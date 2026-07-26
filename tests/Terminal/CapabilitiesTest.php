<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests\Terminal;

use KonradMichalik\PhpProgress\Terminal\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase
{
    private const ENV_KEYS = ['NO_COLOR', 'COLORTERM', 'TERM', 'LC_ALL', 'LC_CTYPE', 'LANG', 'COLUMNS'];

    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] = getenv($key);
            putenv($key); // unset for a clean baseline
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }
    }

    public function testNonTtyHasNoColor(): void
    {
        putenv('COLORTERM=truecolor');
        $caps = Capabilities::classify(false, 80);
        self::assertFalse($caps->tty);
        self::assertSame('none', $caps->colors);
    }

    public function testNoColorEnvDisablesColour(): void
    {
        putenv('NO_COLOR=1');
        putenv('COLORTERM=truecolor');
        self::assertSame('none', Capabilities::classify(true, 80)->colors);
    }

    public function testTruecolorFromColorterm(): void
    {
        putenv('COLORTERM=truecolor');
        self::assertSame('truecolor', Capabilities::classify(true, 80)->colors);
    }

    public function testTruecolorFrom24bit(): void
    {
        putenv('COLORTERM=24bit');
        self::assertSame('truecolor', Capabilities::classify(true, 80)->colors);
    }

    public function test256FromTerm(): void
    {
        putenv('TERM=xterm-256color');
        self::assertSame('256', Capabilities::classify(true, 80)->colors);
    }

    public function testBasicFromGenericTerm(): void
    {
        putenv('TERM=xterm');
        self::assertSame('basic', Capabilities::classify(true, 80)->colors);
    }

    public function testDumbTermHasNoColor(): void
    {
        putenv('TERM=dumb');
        self::assertSame('none', Capabilities::classify(true, 80)->colors);
    }

    public function testEmptyTermHasNoColor(): void
    {
        self::assertSame('none', Capabilities::classify(true, 80)->colors);
    }

    public function testUnicodeFromLang(): void
    {
        putenv('LANG=en_US.UTF-8');
        self::assertTrue(Capabilities::classify(true, 80)->unicode);
    }

    public function testUnicodeFromLcAllUtf8Spelling(): void
    {
        putenv('LC_ALL=de_DE.UTF8');
        self::assertTrue(Capabilities::classify(true, 80)->unicode);
    }

    public function testNonUnicodeLocale(): void
    {
        putenv('LANG=C');
        self::assertFalse(Capabilities::classify(true, 80)->unicode);
    }

    public function testDetectWidthFromColumns(): void
    {
        putenv('COLUMNS=123');
        self::assertSame(123, Capabilities::detectWidth(false));
    }

    public function testDetectWidthDefaultsTo80(): void
    {
        self::assertSame(80, Capabilities::detectWidth(false));
    }

    public function testDetectWidthTtyWithoutColumnsQueriesStty(): void
    {
        // COLUMNS unset + tty => the stty branch runs. Without a real pty it
        // yields no size, so it falls back to 80; either way a positive width.
        self::assertGreaterThan(0, Capabilities::detectWidth(true));
    }

    public function testParseSttySize(): void
    {
        self::assertSame(80, Capabilities::parseSttySize('24 80'));
        self::assertSame(120, Capabilities::parseSttySize("  40 120  "));
        self::assertNull(Capabilities::parseSttySize(''));
        self::assertNull(Capabilities::parseSttySize('garbage'));
    }

    public function testDetectOnNonTtyStream(): void
    {
        putenv('COLUMNS=77');
        $stream = fopen('php://temp', 'w+');
        \assert(\is_resource($stream));
        $caps = Capabilities::detect($stream);
        fclose($stream);

        self::assertFalse($caps->tty);
        self::assertSame(77, $caps->width);
        self::assertSame('none', $caps->colors);
    }
}
