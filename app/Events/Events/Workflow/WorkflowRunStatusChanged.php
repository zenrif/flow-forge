<?php

namespace App\Events\Workflow;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowRunStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $workflowRunId,
        public readonly string $workflowId,
        public readonly string $tenantId,
        public readonly string $status,      // pending | running | success | failed | cancelled | timeout
        public readonly ?\DateTimeInterface $completedAt = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("tenant.{$this->tenantId}.workflows");
    }

    public function broadcastAs(): string
    {
        return 'run.status.changed';
    }
}
