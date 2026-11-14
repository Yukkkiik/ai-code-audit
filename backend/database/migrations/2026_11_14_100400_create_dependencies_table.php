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
        Schema::create('dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_scan_id')->constrained()->cascadeOnDelete();
            $table->string('ecosystem', 20)->default('npm');
            $table->string('name', 214);
            $table->string('version', 100)->nullable();
            $table->string('declared_spec', 1024)->nullable();
            $table->string('scope', 15);
            $table->string('relation', 15);
            $table->string('manifest_path', 1024);
            $table->string('source_file_type', 20);
            $table->string('lock_path', 1024)->nullable();
            $table->string('version_status', 20);
            $table->string('unresolved_reason', 30)->nullable();
            $table->string('osv_status', 15)->default('not_queried');
            $table->timestamps();

            $table->index(['audit_scan_id', 'name']);
            $table->index(['audit_scan_id', 'version_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dependencies');
    }
};
