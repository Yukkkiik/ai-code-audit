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
        Schema::create('audit_scans', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('guest_token_hash', 64)->nullable();
            $table->string('source_type', 10);
            $table->string('repository_name');
            $table->string('repository_url', 2048)->nullable();
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('source_size_bytes')->nullable();
            $table->char('commit_sha', 40)->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('sast_status', 20)->default('pending');
            $table->string('sca_status', 20)->default('pending');
            $table->string('sast_error', 500)->nullable();
            $table->string('sca_error', 500)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->string('health_category', 20)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        // Setiap audit wajib punya pemilik: user terdaftar atau guest token.
        DB::statement('ALTER TABLE audit_scans ADD CONSTRAINT chk_audit_scans_owner CHECK (user_id IS NOT NULL OR guest_token_hash IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_scans');
    }
};
