<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Izinkan user mengakses channel run milik tenant mereka
Broadcast::channel('tenant.{tenantId}.run.{runId}', function ($user, $tenantId, $runId) {
    return (string) $user->tenant_id === (string) $tenantId;
});

// Channel untuk update status workflow (semua run)
Broadcast::channel('tenant.{tenantId}.workflows', function ($user, $tenantId) {
    return (string) $user->tenant_id === (string) $tenantId;
});
