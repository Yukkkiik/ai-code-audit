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
        Schema::create('scan_tool_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_scan_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 20);
            $table->string('status', 20);
            $table->unsignedInteger('files_scanned')->nullable();
            $table->unsignedInteger('findings_count')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['audit_scan_id', 'tool']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_tool_runs');
    }
};
