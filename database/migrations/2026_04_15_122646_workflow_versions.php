<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->jsonb('dag_definition');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['workflow_id', 'version_number']);
            $table->index('workflow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
