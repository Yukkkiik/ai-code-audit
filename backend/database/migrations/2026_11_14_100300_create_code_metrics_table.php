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
        Schema::create('code_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_scan_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 10);
            $table->string('language', 20);
            $table->string('file_path', 1024);
            $table->string('function_name')->nullable();
            $table->unsignedInteger('line_start')->nullable();
            $table->unsignedInteger('loc')->nullable();
            $table->unsignedInteger('sloc')->nullable();
            $table->unsignedInteger('cyclomatic_complexity')->nullable();
            $table->decimal('halstead_volume', 12, 2)->nullable();
            $table->decimal('halstead_difficulty', 10, 2)->nullable();
            $table->decimal('halstead_effort', 14, 2)->nullable();
            $table->decimal('maintainability_index', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['audit_scan_id', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_metrics');
    }
};
