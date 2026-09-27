<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_hareketleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urun_id')->constrained('urunler')->restrictOnDelete();
            $table->string('yon', 10);      // App\Enums\StokYonu
            $table->string('sebep', 20);    // App\Enums\StokSebebi
            $table->decimal('miktar', 12, 2);
            $table->unsignedInteger('top_adedi')->nullable();
            $table->decimal('top_metresi', 10, 2)->nullable();
            // FK, fisler tablosu oluşturulduğunda (Aşama 7) eklenecek.
            $table->unsignedBigInteger('fis_id')->nullable()->index();
            $table->string('aciklama')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['urun_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_hareketleri');
    }
};
