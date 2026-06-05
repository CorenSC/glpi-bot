<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Models\GlpiAiOperationalRun;
use Illuminate\Console\Command;
use Throwable;

final class OperationalRunService
{
    public function run(string $command, callable $callback, array $metadata = []): int
    {
        $started = microtime(true);
        $run = GlpiAiOperationalRun::query()->create([
            'command' => $command,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $metadata,
        ]);

        try {
            $result = $callback($run);
            $exitCode = is_int($result) ? $result : Command::SUCCESS;

            $run->update([
                'status' => $exitCode === Command::SUCCESS ? 'completed' : 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'exit_code' => $exitCode,
            ]);

            return $exitCode;
        } catch (Throwable $throwable) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'exit_code' => Command::FAILURE,
                'error_message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
