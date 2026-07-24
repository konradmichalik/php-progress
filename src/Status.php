<?php

declare(strict_types=1);

namespace KonradMichalik\PhpProgress;

enum Status: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Success = 'success';
    case Failure = 'failure';
    case Warning = 'warning';
    case Stopped = 'stopped';

    public function finished(): bool
    {
        return $this !== self::Idle && $this !== self::Running;
    }
}
