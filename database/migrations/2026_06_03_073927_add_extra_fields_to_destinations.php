<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            if (!Schema::hasColumn('destinations', 'rating')) {
                $table->decimal('rating', 3, 1)->default(0)->after('kapasitas');
            }
            if (!Schema::hasColumn('destinations', 'koordinat')) {
                $table->string('koordinat')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('destinations', 'fasilitas')) {
                $table->text('fasilitas')->nullable()->after('koordinat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn(['rating', 'koordinat', 'fasilitas']);
        });
    }
};
