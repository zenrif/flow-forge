<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->string('step_key');
            $table->enum('step_type', ['http', 'script', 'delay', 'condition']);
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'skipped', 'retrying'])
                ->default('pending');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_run_id', 'status']);
            $table->index('workflow_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_runs');
    }
};
