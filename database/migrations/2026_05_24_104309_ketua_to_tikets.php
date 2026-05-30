<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('nama_ketua')->nullable()->after('ticket_code');
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan'])->nullable()->after('nama_ketua');
            $table->string('no_hp')->nullable()->after('jenis_kelamin');
            $table->string('kebangsaan')->nullable()->default('Indonesia')->after('no_hp');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['nama_ketua', 'jenis_kelamin', 'no_hp', 'kebangsaan']);
        });
    }
};
