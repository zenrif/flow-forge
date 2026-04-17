<?php

namespace App\Events\Workflow;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Di-broadcast ke frontend via WebSocket setiap kali status step berubah.
 * Vue dashboard mendengarkan event ini untuk update UI secara real-time.
 */
class StepStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $workflowRunId,
        public readonly string $tenantId,
        public readonly string $stepKey,
        public readonly string $status,      // pending | running | success | failed | skipped
        public readonly ?array $output,
        public readonly ?string $errorMessage,
        public readonly \DateTimeInterface $timestamp,
    ) {}

    public function broadcastOn(): Channel
    {
        // Setiap tenant punya channel private-nya sendiri → isolasi data
        return new Channel("tenant.{$this->tenantId}.run.{$this->workflowRunId}");
    }

    public function broadcastAs(): string
    {
        return 'step.status.changed';
    }
}
