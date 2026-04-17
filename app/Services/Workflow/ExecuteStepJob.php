<?php

namespace App\Jobs\Workflow;

use App\Events\Workflow\StepStatusChanged;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Services\Workflow\DAG\DagNode;
use App\Exceptions\Services\Workflow\DAG\Exceptions\StepExecutionException;
use App\Services\Workflow\Executors\StepExecutorInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ExecuteStepJob — menjalankan SATU step dari workflow.
 *
 * Retry logic: Laravel's built-in retry dengan custom backoff.
 * Exponential backoff: [10s, 30s, 90s] untuk max 3 retry.
 *
 * Flow:
 * 1. Update step_runs.status = 'running'
 * 2. Broadcast StepStatusChanged (frontend update)
 * 3. Jalankan executor yang sesuai dengan step type
 * 4. Simpan output, update status ke 'success'
 * 5. Jika gagal: update status ke 'failed'/'retrying', schedule retry
 * 6. Setelah step selesai: notify WorkflowOrchestrator
 */
class ExecuteStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries    = 1; // dikontrol manual via retry logic di bawah
    public int $timeout  = 120;

    public function __construct(
        public readonly string  $workflowRunId,
        public readonly string  $stepRunId,
        public readonly DagNode $node,
        public readonly array   $context,         // output step sebelumnya
        public readonly int     $attempt = 1,
    ) {}

    public function handle(): void
    {
        $stepRun     = StepRun::findOrFail($this->stepRunId);
        $workflowRun = WorkflowRun::findOrFail($this->workflowRunId);

        // Batalkan jika workflow sudah di-cancel atau timeout
        if (in_array($workflowRun->status, ['cancelled', 'timeout', 'failed'], true)) {
            $this->updateStepStatus($stepRun, 'skipped');
            return;
        }

        $this->updateStepStatus($stepRun, 'running');

        try {
            $executor = $this->resolveExecutor($this->node->type);
            $output   = $executor->execute($this->node, $this->context);

            $this->updateStepStatus($stepRun, 'success', output: $output);

            // Beritahu orkestrator bahwa step ini selesai
            // (akan dispatch job untuk step berikutnya yang unlocked)
            AdvanceWorkflowJob::dispatch($this->workflowRunId, $this->node->key, 'success')
                ->onQueue('workflow-orchestration');
        } catch (StepExecutionException $e) {
            Log::warning("Step '{$this->node->key}' gagal (attempt {$this->attempt}): {$e->getMessage()}");

            $shouldRetry = $e->retryable && $this->attempt < $this->node->max_retries;

            if ($shouldRetry) {
                $this->scheduleRetry($stepRun, $e->getMessage());
            } else {
                $this->updateStepStatus($stepRun, 'failed', error: $e->getMessage());

                AdvanceWorkflowJob::dispatch($this->workflowRunId, $this->node->key, 'failed')
                    ->onQueue('workflow-orchestration');
            }
        } catch (\Throwable $e) {
            // Error yang tidak terduga (bukan StepExecutionException)
            Log::error("Unexpected error di step '{$this->node->key}': {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateStepStatus($stepRun, 'failed', error: $e->getMessage());

            AdvanceWorkflowJob::dispatch($this->workflowRunId, $this->node->key, 'failed')
                ->onQueue('workflow-orchestration');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Exponential backoff retry scheduling
    // Attempt 1 gagal → retry setelah 10 detik
    // Attempt 2 gagal → retry setelah 30 detik
    // Attempt 3 gagal → retry setelah 90 detik
    // ─────────────────────────────────────────────────────────────

    private function scheduleRetry(StepRun $stepRun, string $errorMessage): void
    {
        $backoffSeconds = $this->calculateBackoff($this->attempt);

        $this->updateStepStatus($stepRun, 'retrying', error: $errorMessage);

        // Buat step run baru untuk attempt berikutnya
        $nextStepRun = StepRun::create([
            'workflow_run_id' => $this->workflowRunId,
            'step_key'        => $this->node->key,
            'step_type'       => $this->node->type,
            'status'          => 'pending',
            'attempt'         => $this->attempt + 1,
            'input'           => $this->context,
        ]);

        self::dispatch(
            workflowRunId: $this->workflowRunId,
            stepRunId: $nextStepRun->id,
            node: $this->node,
            context: $this->context,
            attempt: $this->attempt + 1,
        )
            ->onQueue('workflow-steps')
            ->delay(now()->addSeconds($backoffSeconds));

        Log::info("Step '{$this->node->key}' dijadwalkan retry attempt " . ($this->attempt + 1) . " dalam {$backoffSeconds} detik.");
    }

    private function calculateBackoff(int $attempt): int
    {
        // Formula: base * 3^(attempt-1) dengan sedikit jitter
        $base   = 10;
        $jitter = rand(0, 5);
        return (int) ($base * (3 ** ($attempt - 1))) + $jitter;
        // attempt 1 → 10–15s
        // attempt 2 → 30–35s
        // attempt 3 → 90–95s
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function updateStepStatus(
        StepRun $stepRun,
        string  $status,
        ?array  $output = null,
        ?string $error  = null,
    ): void {
        $stepRun->update([
            'status'        => $status,
            'output'        => $output,
            'error_message' => $error,
            'started_at'    => $stepRun->started_at ?? now(),
            'completed_at'  => in_array($status, ['success', 'failed', 'skipped']) ? now() : null,
        ]);

        broadcast(new StepStatusChanged(
            workflowRunId: $this->workflowRunId,
            tenantId: WorkflowRun::find($this->workflowRunId)?->workflow?->tenant_id ?? '',
            stepKey: $this->node->key,
            status: $status,
            output: $output,
            errorMessage: $error,
            timestamp: now(),
        ))->toOthers();
    }

    private function resolveExecutor(string $type): StepExecutorInterface
    {
        return match ($type) {
            'http'      => app(\App\Services\Workflow\Executors\HttpStepExecutor::class),
            'script'    => app(\App\Services\Workflow\Executors\ScriptStepExecutor::class),
            'delay'     => app(\App\Services\Workflow\Executors\DelayStepExecutor::class),
            'condition' => app(\App\Services\Workflow\Executors\ConditionStepExecutor::class),
            default     => throw new \InvalidArgumentException("Unknown step type: {$type}"),
        };
    }
}
