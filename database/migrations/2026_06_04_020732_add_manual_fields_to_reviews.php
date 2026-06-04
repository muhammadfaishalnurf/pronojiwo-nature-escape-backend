<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // Nama reviewer manual (bisa dari google maps)
            if (!Schema::hasColumn('reviews', 'nama')) {
                $table->string('nama')->nullable()->after('user_id');
            }
            // Label tanggal custom: "1 minggu yang lalu", "2 bulan yang lalu", dll
            if (!Schema::hasColumn('reviews', 'tanggal_label')) {
                $table->string('tanggal_label')->nullable()->after('rating');
            }
            // user_id boleh null untuk review manual
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['nama', 'tanggal_label']);
        });
    }
};