<?php

declare(strict_types=1);

// Worker for the SIGINT/SIGTERM regression test. Starts a live bar with
// simulated TTY caps, signals readiness via $argv[2], then spins until a
// signal arrives. php-progress's handler must restore the cursor and re-raise.

require __DIR__ . '/bootstrap.php';

use KonradMichalik\PhpProgress\Live;
use KonradMichalik\PhpProgress\Terminal\Capabilities;

$out = fopen($argv[1], 'w');
$caps = new Capabilities(true, 80, 'truecolor', true);
$bar = Live::bar(1000, 'interruptible')->to($out)->caps($caps)->start();

file_put_contents($argv[2], 'ready');

$i = 0;
while (true) {
    usleep(5000);
    $bar->progress($i++ % 1000);
}
