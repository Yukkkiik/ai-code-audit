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
        Schema::create('vulnerabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dependency_id')->constrained()->cascadeOnDelete();
            $table->string('osv_id', 100);
            $table->json('aliases')->nullable();
            $table->string('summary', 1000)->nullable();
            $table->mediumText('details')->nullable();
            $table->string('severity_label', 10)->nullable();
            $table->json('severity_vectors')->nullable();
            $table->json('affected_ranges')->nullable();
            $table->json('fixed_versions')->nullable();
            $table->json('reference_urls')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();

            $table->unique(['dependency_id', 'osv_id']);
            $table->index(['audit_scan_id', 'severity_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vulnerabilities');
    }
};
