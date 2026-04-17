<?php

namespace App\Jobs\Workflow;

use App\Events\Workflow\WorkflowRunStatusChanged;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Services\Workflow\DAG\DagNode;
use App\Services\Workflow\DAG\DagParser;
use App\Services\Workflow\DAG\TopologicalSorter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * AdvanceWorkflowJob — otak dari orkestrator.
 *
 * Dipanggil setiap kali sebuah step selesai (sukses atau gagal).
 * Tugasnya:
 * 1. Cek apakah semua dependency step berikutnya sudah terpenuhi
 * 2. Dispatch ExecuteStepJob untuk step yang siap dijalankan
 * 3. Jika semua step selesai → update workflow run status
 * 4. Jika ada step yang gagal → tentukan apakah workflow harus berhenti
 * 5. Pantau global timeout
 */
class AdvanceWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $workflowRunId,
        public readonly string $completedStepKey,
        public readonly string $completedStepStatus, // 'success' | 'failed'
    ) {}

    public function handle(DagParser $parser, TopologicalSorter $sorter): void
    {
        DB::transaction(function () use ($parser, $sorter) {
            $workflowRun = WorkflowRun::with(['version', 'stepRuns', 'workflow'])
                ->lockForUpdate()
                ->findOrFail($this->workflowRunId);

            // Abaikan jika run sudah terminal
            if (in_array($workflowRun->status, ['success', 'failed', 'cancelled', 'timeout'])) {
                return;
            }

            // Cek global timeout
            if ($this->isGlobalTimeout($workflowRun)) {
                $this->finishRun($workflowRun, 'timeout');
                return;
            }

            $definition = $workflowRun->version->dag_definition;
            $nodes      = $parser->parse($definition);

            // Ambil semua step yang sudah selesai
            $completedStepRuns = $workflowRun->stepRuns
                ->groupBy('step_key')
                ->map(fn($runs) => $runs->sortByDesc('attempt')->first()); // ambil attempt terbaru

            $completedKeys = $completedStepRuns
                ->where('status', 'success')
                ->keys()
                ->toArray();

            $failedKeys = $completedStepRuns
                ->where('status', 'failed')
                ->keys()
                ->toArray();

            $skippedKeys = $completedStepRuns
                ->where('status', 'skipped')
                ->keys()
                ->toArray();

            // Kumpulkan output dari semua step sukses sebagai context
            $context = $completedStepRuns
                ->where('status', 'success')
                ->mapWithKeys(fn($run) => [$run->step_key => $run->output ?? []])
                ->toArray();

            // Kumpulkan step keys yang harus di-skip dari condition branches
            $conditionSkips = $completedStepRuns
                ->where('status', 'success')
                ->filter(fn($run) => $run->step_type === 'condition')
                ->flatMap(fn($run) => $run->output['skip_steps'] ?? [])
                ->toArray();

            $allSkipped = array_merge($skippedKeys, $conditionSkips);

            // Cek apakah ada step gagal yang menghentikan seluruh workflow
            if (!empty($failedKeys)) {
                $this->finishRun($workflowRun, 'failed');
                return;
            }

            // Temukan step yang siap dijalankan:
            // dependency-nya semua sudah sukses/skipped, dan belum pernah jalan
            $runningKeys  = $completedStepRuns->where('status', 'running')->keys()->toArray();
            $retryingKeys = $completedStepRuns->where('status', 'retrying')->keys()->toArray();
            $processedKeys = array_merge($completedKeys, $failedKeys, $allSkipped, $runningKeys, $retryingKeys);

            $readySteps = [];
            foreach ($nodes as $key => $node) {
                if (in_array($key, $processedKeys, true)) {
                    continue; // sudah diproses
                }

                if (in_array($key, $conditionSkips, true)) {
                    // Step ini harus di-skip karena condition branch
                    $this->createSkippedStepRun($workflowRun->id, $node);
                    continue;
                }

                // Semua dependency harus sudah sukses atau skipped
                $depsReady = collect($node->depends_on)
                    ->every(fn($dep) => in_array($dep, array_merge($completedKeys, $allSkipped), true));

                if ($depsReady) {
                    $readySteps[] = $node;
                }
            }

            // Dispatch jobs untuk semua step yang siap (bisa paralel)
            foreach ($readySteps as $node) {
                $stepRun = StepRun::create([
                    'workflow_run_id' => $this->workflowRunId,
                    'step_key'        => $node->key,
                    'step_type'       => $node->type,
                    'status'          => 'pending',
                    'attempt'         => 1,
                    'input'           => $context,
                ]);

                ExecuteStepJob::dispatch(
                    workflowRunId: $this->workflowRunId,
                    stepRunId: $stepRun->id,
                    node: $node,
                    context: $context,
                )->onQueue('workflow-steps');
            }

            // Cek apakah semua step sudah selesai
            $totalSteps    = count($nodes);
            $doneCount     = count($completedKeys) + count($allSkipped) + count($conditionSkips);

            // Juga hitung fresh skipped yang baru saja kita buat
            $freshSkipped  = count(array_filter($readySteps, fn($n) => in_array($n->key, $conditionSkips, true)));

            if ($doneCount + $freshSkipped >= $totalSteps && empty($readySteps)) {
                $this->finishRun($workflowRun, 'success');
            }
        });
    }

    private function isGlobalTimeout(WorkflowRun $run): bool
    {
        // Global timeout: 1 jam default, bisa dikonfigurasi per workflow
        $maxMinutes = config('workflow.global_timeout_minutes', 60);
        return $run->started_at && $run->started_at->diffInMinutes(now()) > $maxMinutes;
    }

    private function finishRun(WorkflowRun $run, string $status): void
    {
        $run->update([
            'status'       => $status,
            'completed_at' => now(),
        ]);

        broadcast(new WorkflowRunStatusChanged(
            workflowRunId: $run->id,
            workflowId: $run->workflow_id,
            tenantId: $run->workflow->tenant_id,
            status: $status,
            completedAt: now(),
        ));
    }

    private function createSkippedStepRun(string $workflowRunId, DagNode $node): void
    {
        StepRun::create([
            'workflow_run_id' => $workflowRunId,
            'step_key'        => $node->key,
            'step_type'       => $node->type,
            'status'          => 'skipped',
            'attempt'         => 1,
        ]);
    }
}
