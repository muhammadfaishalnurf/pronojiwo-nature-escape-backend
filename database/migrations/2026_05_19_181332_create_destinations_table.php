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
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('nama_wisata');
            $table->text('deskripsi')->nullable();
            $table->string('lokasi_rute')->nullable();
            $table->decimal('harga_tiket', 10, 2)->default(0);
            $table->integer('kapasitas')->default(100);
            $table->string('foto')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};
