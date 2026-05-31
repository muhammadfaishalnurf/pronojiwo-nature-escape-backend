<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya tambah kolom kalau belum ada
        if (!Schema::hasColumn('payments', 'snap_token')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('snap_token')->nullable()->after('order_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'snap_token')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('snap_token');
            });
        }
    }
};
