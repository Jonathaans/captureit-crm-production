<?php

declare(strict_types=1);

require_once __DIR__.'/performance_test/CrmPerformanceDataV1.php';

try {
    exit(\CrmPerformanceTest\CrmPerformanceDataV1::cleanup(dirname(__DIR__), $argv));
} catch (Throwable $exception) {
    fwrite(STDERR, "\nCLEANUP GAGAL: {$exception->getMessage()}\n");
    exit(1);
}
