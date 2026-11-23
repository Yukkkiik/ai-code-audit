<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NULL = daftar `versions` tidak diketahui (detail gagal diambil, daftar tidak lengkap, atau baris lama);
     * `[]` = OSV tidak mencantumkan `versions`. Tidak ada backfill.
     */
    public function up(): void
    {
        Schema::table('vulnerabilities', function (Blueprint $table) {
            $table->json('affected_versions')->nullable()->after('affected_ranges');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vulnerabilities', function (Blueprint $table) {
            $table->dropColumn('affected_versions');
        });
    }
};
