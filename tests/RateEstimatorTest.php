<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\RateEstimator;
use PHPUnit\Framework\TestCase;

final class RateEstimatorTest extends TestCase
{
    public function testRateNullWithoutEnoughSamples(): void
    {
        $r = new RateEstimator();
        self::assertNull($r->rate());
        $r->add(0.0, 0.0);
        self::assertNull($r->rate()); // one sample
    }

    public function testRateNullWhenWindowTooShort(): void
    {
        $r = new RateEstimator();
        $r->add(0.0, 0.0);
        $r->add(100.0, 10.0); // dt 100ms < 200ms threshold
        self::assertNull($r->rate());
    }

    public function testRateNullWhenNoProgress(): void
    {
        $r = new RateEstimator();
        $r->add(0.0, 5.0);
        $r->add(1000.0, 5.0); // c1 <= c0
        self::assertNull($r->rate());
    }

    public function testRateComputesUnitsPerSecond(): void
    {
        $r = new RateEstimator();
        $r->add(0.0, 0.0);
        $r->add(1000.0, 50.0); // 50 units in 1s
        self::assertSame(50.0, $r->rate());
    }

    public function testHotLoopUpdatesAreCoalesced(): void
    {
        $r = new RateEstimator();
        $r->add(0.0, 0.0);
        // Sub-50ms updates overwrite the last sample's completed value while
        // keeping its timestamp, so the loop stays O(1) instead of appending.
        $r->add(10.0, 1.0);   // overwrites the [0,0] sample -> [0,1]
        $r->add(20.0, 2.0);   // -> [0,2]
        $r->add(1000.0, 100.0); // >=50ms gap -> appends [1000,100]
        // c0=2 (kept from the coalesced first sample), c1=100 over 1s => 98/s.
        self::assertSame(98.0, $r->rate());
    }

    public function testOldSamplesFallOutOfWindow(): void
    {
        $r = new RateEstimator(windowMs: 1000.0);
        for ($t = 0; $t <= 5000; $t += 250) {
            $r->add((float) $t, (float) $t / 100.0);
        }
        // Only the last ~1s window contributes; a positive rate is produced.
        $rate = $r->rate();
        self::assertNotNull($rate);
        self::assertGreaterThan(0.0, $rate);
    }

    public function testEtaNullWithoutTotal(): void
    {
        $r = new RateEstimator();
        self::assertNull($r->eta(null, 0.0));
        self::assertNull($r->eta(0.0, 0.0));
    }

    public function testEtaNullWithoutRate(): void
    {
        $r = new RateEstimator();
        $r->add(0.0, 0.0); // not enough for a rate
        self::assertNull($r->eta(100.0, 0.0));
    }

    public function testEtaComputesSecondsRemaining(): void
    {
        $r = new RateEstimator();
        $r->add(0.0, 0.0);
        $r->add(1000.0, 50.0); // 50/s
        // 50 remaining of 100 at 50/s => 1s
        self::assertSame(1.0, $r->eta(100.0, 50.0));
    }
}
