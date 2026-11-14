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
        Schema::create('scan_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_scan_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 20);
            $table->string('rule_id');
            $table->string('category', 30);
            $table->string('severity', 10);
            $table->string('file_path', 1024);
            $table->unsignedInteger('line_start')->nullable();
            $table->unsignedInteger('line_end')->nullable();
            $table->unsignedInteger('column_start')->nullable();
            $table->text('code_snippet')->nullable();
            $table->text('message');
            $table->string('cwe', 50)->nullable();
            $table->char('fingerprint', 64);
            $table->timestamps();

            $table->unique(['audit_scan_id', 'fingerprint']);
            $table->index(['audit_scan_id', 'severity']);
            $table->index(['audit_scan_id', 'tool']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scan_findings');
    }
};
