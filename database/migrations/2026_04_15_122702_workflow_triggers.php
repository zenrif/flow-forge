<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->enum('type', ['manual', 'cron', 'webhook']);
            $table->string('cron_expression')->nullable();
            $table->string('webhook_token', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('workflow_id');
            $table->index('webhook_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_triggers');
    }
};
