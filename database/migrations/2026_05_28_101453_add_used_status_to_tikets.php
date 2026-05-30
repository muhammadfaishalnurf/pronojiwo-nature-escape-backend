<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable()->after('order_id');
        });

        // Update enum status untuk tambah 'used'
        DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('pending','confirmed','cancelled','used') DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('used_at');
        });
        DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('pending','confirmed','cancelled') DEFAULT 'pending'");
    }
};
