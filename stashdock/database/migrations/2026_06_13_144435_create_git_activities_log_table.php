<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('git_activities_log', function (Blueprint $table) {
            $table->id();
            $table->string('project_name')->nullable(false);
            $table->string('activity_type')->nullable(false); // 'commit' or 'push' — enforced at application layer
            $table->date('executed_at')->nullable(false);
            $table->timestamps();

            // Composite index for fast chart queries (date + type grouping/filtering)
            $table->index(['executed_at', 'activity_type'], 'idx_activities_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('git_activities_log');
    }
};
