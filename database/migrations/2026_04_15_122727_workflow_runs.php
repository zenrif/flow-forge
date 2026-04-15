<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignUuid('version_id')->constrained('workflow_versions');
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'cancelled', 'timeout'])
                ->default('pending');
            $table->enum('trigger_type', ['manual', 'cron', 'webhook']);
            $table->jsonb('input_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Index penting untuk query dashboard & history
            $table->index(['workflow_id', 'status']);
            $table->index(['workflow_id', 'started_at']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
