<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('midtrans_data')->nullable();
            $table->timestamps();
        });

        // Tambah kolom order_id ke tabel tickets
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('order_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
