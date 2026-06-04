<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('destinations', 'kategori')) {
            Schema::table('destinations', function (Blueprint $table) {
                $table->string('kategori')->default('Wisata Alam')->after('nama_wisata');
            });
        }

        // Auto-fill kategori dari nama yang sudah ada
        \DB::statement("
            UPDATE destinations SET kategori =
                CASE
                    WHEN LOWER(nama_wisata) LIKE '%air terjun%' THEN 'Air Terjun'
                    WHEN LOWER(nama_wisata) LIKE '%panorama%'   THEN 'Panorama'
                    WHEN LOWER(nama_wisata) LIKE '%bukit%'      THEN 'Panorama'
                    WHEN LOWER(nama_wisata) LIKE '%hutan%'      THEN 'Hutan'
                    WHEN LOWER(nama_wisata) LIKE '%pinus%'      THEN 'Hutan'
                    ELSE 'Wisata Alam'
                END
        ");
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};