<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress\Tests;

use KonradMichalik\PhpProgress\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    #[DataProvider('finishedProvider')]
    public function testFinished(Status $status, bool $finished): void
    {
        self::assertSame($finished, $status->finished());
    }

    /** @return list<array{Status, bool}> */
    public static function finishedProvider(): array
    {
        return [
            [Status::Idle, false],
            [Status::Running, false],
            [Status::Success, true],
            [Status::Failure, true],
            [Status::Warning, true],
            [Status::Stopped, true],
        ];
    }
}
