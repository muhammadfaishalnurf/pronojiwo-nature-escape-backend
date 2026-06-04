<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // Seed default values
        $defaults = [
            'app_name'        => 'Pronojiwo Nature Escape',
            'contact_phone'   => '6281234567890',
            'contact_email'   => 'hello@pronojiwonature.id',
            'contact_address' => 'Kecamatan Pronojiwo, Lumajang, Jawa Timur',
            'about_text'      => 'Pronojiwo Nature Escape adalah platform reservasi wisata alam terpadu.',
        ];

        foreach ($defaults as $key => $value) {
            \DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};