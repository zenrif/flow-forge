<?php

namespace App\Services\Workflow\Executors;

use App\Services\Workflow\DAG\DagNode;
use App\Services\Workflow\DAG\Exceptions\StepExecutionException;

/**
 * Menjalankan step bertipe 'script'.
 *
 * Config yang dibutuhkan:
 * {
 *   "command":  "php artisan import:data --date={{fetch_data.body.date}}",
 *   "env":      { "APP_ENV": "production" }  // opsional
 * }
 *
 * KEAMANAN: Command di-whitelist via ALLOWED_COMMANDS di config.
 * Tidak pernah mengeksekusi input user secara langsung.
 */
class ScriptStepExecutor implements StepExecutorInterface
{
    public function execute(DagNode $node, array $context): array
    {
        $command = $this->interpolate($node->config['command'], $context);

        // Whitelist check — hanya command yang terdaftar yang boleh dijalankan
        $this->assertCommandAllowed($command);

        $env     = $node->config['env'] ?? [];
        $timeout = $node->timeout_seconds;

        // Set environment variables untuk subprocess
        $envString = collect($env)
            ->map(fn($v, $k) => "{$k}=" . escapeshellarg($v))
            ->join(' ');

        $fullCommand = $envString
            ? "{$envString} timeout {$timeout} {$command} 2>&1"
            : "timeout {$timeout} {$command} 2>&1";

        $output   = [];
        $exitCode = 0;
        exec($fullCommand, $output, $exitCode);

        if ($exitCode !== 0) {
            // exit code 124 = timeout dari command `timeout`
            $isTimeout = ($exitCode === 124);
            throw new StepExecutionException(
                $isTimeout
                    ? "Script timeout setelah {$timeout} detik."
                    : "Script keluar dengan exit code {$exitCode}: " . implode("\n", $output),
                stepKey: $node->key,
                retryable: !$isTimeout,
            );
        }

        return [
            'exit_code' => $exitCode,
            'output'    => implode("\n", $output),
        ];
    }

    private function assertCommandAllowed(string $command): void
    {
        $allowedPrefixes = config('workflow.allowed_script_prefixes', [
            'php artisan',
            'node ',
            'python ',
        ]);

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($command, $prefix)) {
                return;
            }
        }

        throw new StepExecutionException(
            "Command tidak diizinkan: '{$command}'.",
            stepKey: '',
            retryable: false,
        );
    }

    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function (array $matches) use ($context) {
                $parts = explode('.', trim($matches[1]));
                $value = $context;
                foreach ($parts as $part) {
                    $value = $value[$part] ?? null;
                }
                return (string) ($value ?? $matches[0]);
            },
            $template
        );
    }
}
