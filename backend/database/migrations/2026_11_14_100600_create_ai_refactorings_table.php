<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_refactorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_finding_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vulnerability_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20);
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 20);
            $table->string('status', 25)->default('pending');
            $table->text('original_code')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('impact')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('suggested_code')->nullable();
            $table->text('rationale')->nullable();
            $table->json('validation_errors')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->index('audit_scan_id');
            $table->index('scan_finding_id');
            $table->index('vulnerability_id');
        });

        // Tepat satu target: temuan SAST atau kerentanan dependency.
        DB::statement('ALTER TABLE ai_refactorings ADD CONSTRAINT chk_ai_refactorings_single_target CHECK ((scan_finding_id IS NULL) <> (vulnerability_id IS NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_refactorings');
    }
};
