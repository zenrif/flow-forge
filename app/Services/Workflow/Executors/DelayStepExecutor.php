<?php

namespace App\Services\Workflow\Executors;

use App\Services\Workflow\DAG\DagNode;

/**
 * Menjalankan step bertipe 'delay'.
 *
 * Config yang dibutuhkan:
 * {
 *   "seconds": 5
 * }
 *
 * Catatan: untuk delay panjang (> 60 detik), pertimbangkan menggunakan
 * Laravel's delayed dispatch di WorkflowOrchestrator, bukan sleep() di sini.
 */
class DelayStepExecutor implements StepExecutorInterface
{
    public function execute(DagNode $node, array $context): array
    {
        $seconds = (int) ($node->config['seconds'] ?? 1);
        $seconds = max(1, min($seconds, 300)); // batasi 1–300 detik

        sleep($seconds);

        return [
            'delayed_seconds' => $seconds,
        ];
    }
}
