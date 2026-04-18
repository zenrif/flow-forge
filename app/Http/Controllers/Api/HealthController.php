<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkflowRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function stats(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $since    = now()->subHours(24);

        $runs = WorkflowRun::where('tenant_id_through_workflow', $tenantId) // via join
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $since)
            ->get();

        $total   = $runs->count();
        $success = $runs->where('status', 'success')->count();
        $failed  = $runs->where('status', 'failed')->count();
        $active  = WorkflowRun::whereHas('workflow', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'running')->count();

        $avgMs = $runs->whereNotNull('completed_at')->avg(function ($r) {
            return strtotime($r->completed_at) * 1000 - strtotime($r->started_at) * 1000;
        }) ?? 0;

        return response()->json([
            'active_runs'        => $active,
            'success_count_24h'  => $success,
            'failed_count_24h'   => $failed,
            'success_rate_24h'   => $total > 0 ? round($success / $total * 100, 1) : 0,
            'avg_duration_ms_24h' => (int) $avgMs,
            'total_runs_24h'     => $total,
        ]);
    }
}
