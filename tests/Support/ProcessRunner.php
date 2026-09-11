<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class ProcessRunner
{
    /**
     * @param list<string> $arguments
     */
    public static function run(array $arguments, float $timeoutSeconds = 5.0): ProcessResult
    {
        $process = proc_open($arguments, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start PHP child process.');
        }

        $deadline = microtime(true) + $timeoutSeconds;
        $timedOut = false;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process);
                break;
            }
            usleep(10_000);
        } while (true);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return new ProcessResult($stdout, $stderr, $exitCode, $timedOut);
    }
}
