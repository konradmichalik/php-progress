<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Frame;
use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Support\Text;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

final class LiveSpinnerTest extends TestCase
{
    public function testFramesAnimateUniformWidthAndEndIcon(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = $this->spinner('Connecting', $this->tty(100), $clock, $stream)->start();
        for ($i = 0; $i < 12; $i++) {
            $clock->ms += 90;
            $sp->tick();
        }
        $sp->set('host', 'staging.example.org');
        $clock->ms += 90;
        $sp->tick();
        $sp->succeed('Connected');

        $frames = $this->frames($read());
        $glyphs = array_unique(array_map(static fn ($f) => mb_substr($f, 0, 1), array_slice($frames, 0, 12)));
        self::assertGreaterThan(3, count($glyphs), 'glyph advances with wall clock');
        $final = (string) end($frames);
        self::assertStringContainsString('✔', $final);
        self::assertStringContainsString('Connected', $final);
        self::assertNotSame([], array_filter($frames, static fn ($f) => str_contains($f, 'example.org')));
    }

    public function testStarTwinkle(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = Live::spinner('Cogitating')->style('star')->color('#d97757')
            ->to($stream)->caps($this->tty(72))->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 10; $i++) {
            $clock->ms += 90;
            $sp->tick();
        }
        $sp->succeed('Done');
        $glyphs = [];
        foreach ($this->frames($read()) as $f) {
            $f = ltrim($f);
            if ($f !== '') {
                $glyphs[] = mb_substr($f, 0, 1);
            }
        }
        $sparkle = array_intersect($glyphs, ['·', '✢', '✳', '✶', '✻', '✽']);
        self::assertGreaterThanOrEqual(4, count(array_unique($sparkle)));
    }

    public function testStarAsciiFallback(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = Live::spinner('Working')->style('star')
            ->to($stream)->caps(new Capabilities(true, 72, 'truecolor', false))
            ->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 6; $i++) {
            $clock->ms += 100;
            $sp->tick();
        }
        $sp->stop();
        $raw = $read();
        self::assertNotSame(1, preg_match('/[·✢✳✶✻✽]/u', $raw));
        self::assertStringContainsString('*', $raw);
    }

    public function testProceduralWaveAndCometConstantLeadWidth(): void
    {
        foreach (['wave' => 5, 'comet' => 9] as $style => $expectWidth) {
            [$stream, $read] = $this->memStream();
            $clock = new Clock();
            $sp = Live::spinner('Working')->style($style)
                ->to($stream)->caps($this->tty(72))->clock($clock)->handleSignals(false)->start();
            for ($i = 0; $i < 12; $i++) {
                $clock->ms += 100;
                $sp->tick();
            }
            $sp->succeed('ok');
            $leads = [];
            $widths = [];
            foreach ($this->frames($read()) as $f) {
                $f = rtrim($f);
                if ($f === '') {
                    continue;
                }
                $lead = explode(' ', ltrim($f))[0];
                if ($lead === '✔') {
                    continue;
                }
                $widths[Text::width($lead)] = true;
                $leads[] = $lead;
            }
            self::assertSame([$expectWidth], array_keys($widths), "{$style} constant width");
            self::assertGreaterThanOrEqual(4, count(array_unique($leads)), "{$style} animates");
        }
    }

    public function testCometTailFadesInMonochrome(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = Live::spinner('x')->style('comet')
            ->to($stream)->caps(new Capabilities(true, 72, 'none', true))
            ->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 6; $i++) {
            $clock->ms += 110;
            $sp->tick();
        }
        $sp->stop();
        self::assertSame(1, preg_match('/[▓▒░]/u', $read()));
    }

    public function testProceduralStyleFallsBackToLineOnNonUtf8(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = Live::spinner('x')->style('wave')
            ->to($stream)->caps(new Capabilities(true, 72, 'truecolor', false))
            ->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 5; $i++) {
            $clock->ms += 120;
            $sp->tick();
        }
        $sp->stop();
        self::assertNotSame(1, preg_match('/[▁▂▃▄▅▆▇█]/u', $read()));
    }

    public function testCustomSpinnerFnAnimates(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $fn = static function (float $phase, Frame $frame): string {
            $n = (int) round($phase * 3) % 4;

            return str_repeat('=', $n) . '>' . str_repeat(' ', 3 - $n);
        };
        $sp = Live::spinner('Loading')->spinnerFn($fn, periodMs: 400)
            ->to($stream)->caps($this->tty(72))->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 8; $i++) {
            $clock->ms += 100;
            $sp->tick();
        }
        $sp->succeed('done');
        $leads = [];
        foreach ($this->frames($read()) as $f) {
            $f = rtrim($f);
            if ($f !== '' && !str_contains($f, '✔')) {
                $leads[] = mb_substr($f, 0, 4);
            }
        }
        self::assertGreaterThanOrEqual(2, count(array_unique($leads)));
    }

    public function testCustomFramesWithColor(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = Live::spinner('Working')->frames(['◐', '◓', '◑', '◒'], intervalMs: 120)->color('#7c9cff')
            ->to($stream)->caps($this->tty(60))->clock($clock)->handleSignals(false)->start();
        for ($i = 0; $i < 4; $i++) {
            $clock->ms += 120;
            $sp->tick();
        }
        $sp->stop();
        self::assertStringContainsString("\e[38;2;124;156;255m", $read());
    }

    public function testEmptyLabelSpinnerStillShowsFinalText(): void
    {
        [$stream, $read] = $this->memStream();
        $clock = new Clock();
        $sp = $this->spinner('', $this->tty(60), $clock, $stream)->start();
        $clock->ms += 90;
        $sp->tick();
        $sp->succeed('Schema up to date');
        self::assertStringContainsString('Schema up to date', Text::stripAnsi($read()));
    }

    private function spinner(string $label, Capabilities $caps, Clock $clock, $stream): Live
    {
        return Live::spinner($label)->to($stream)->caps($caps)->clock($clock)->handleSignals(false);
    }
}
