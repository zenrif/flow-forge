<?php

namespace App\Jobs\Workflow;

use App\Events\Workflow\WorkflowRunStatusChanged;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Services\Workflow\DAG\DagParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * StartWorkflowRunJob — entry point untuk menjalankan workflow.
 *
 * Dipanggil oleh WorkflowController (manual trigger)
 * atau WorkflowSchedulerCommand (cron trigger)
 * atau WebhookController (webhook trigger).
 *
 * Flow:
 * 1. Update workflow_run.status = 'running'
 * 2. Parse DAG → dapatkan semua node
 * 3. Dispatch AdvanceWorkflowJob dengan completedStepKey = '' (permulaan)
 *    AdvanceWorkflow akan menemukan step tanpa dependency (wave 0) dan dispatch-nya
 */
class StartWorkflowRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    public function __construct(
        public readonly string $workflowRunId,
    ) {}

    public function handle(DagParser $parser): void
    {
        $workflowRun = WorkflowRun::with(['version', 'workflow'])->findOrFail($this->workflowRunId);

        // Validasi DAG sebelum mulai eksekusi
        try {
            $parser->parse($workflowRun->version->dag_definition);
        } catch (\Exception $e) {
            $workflowRun->update([
                'status'       => 'failed',
                'completed_at' => now(),
            ]);

            broadcast(new WorkflowRunStatusChanged(
                workflowRunId: $workflowRun->id,
                workflowId: $workflowRun->workflow_id,
                tenantId: $workflowRun->workflow->tenant_id,
                status: 'failed',
                completedAt: now(),
            ));
            return;
        }

        $workflowRun->update([
            'status'     => 'running',
            'started_at' => now(),
        ]);

        broadcast(new WorkflowRunStatusChanged(
            workflowRunId: $workflowRun->id,
            workflowId: $workflowRun->workflow_id,
            tenantId: $workflowRun->workflow->tenant_id,
            status: 'running',
        ));

        // Serahkan ke AdvanceWorkflowJob untuk dispatch wave pertama
        // completedStepKey = '__start__' sebagai sentinel value
        AdvanceWorkflowJob::dispatch($this->workflowRunId, '__start__', 'success')
            ->onQueue('workflow-orchestration');
    }
}
