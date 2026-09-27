<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urunler', function (Blueprint $table) {
            $table->id();
            $table->string('urun_kodu', 50)->unique();
            $table->string('ad');
            $table->foreignId('kategori_id')->constrained('kategoriler')->restrictOnDelete();
            $table->string('renk', 100)->nullable();
            $table->string('desen', 100)->nullable();
            $table->unsignedSmallInteger('en_cm')->nullable();
            $table->string('birim', 10)->default('metre');
            $table->decimal('varsayilan_top_metresi', 10, 2)->nullable();
            $table->decimal('alis_fiyati', 12, 2)->nullable();
            $table->decimal('satis_fiyati', 12, 2)->nullable();
            $table->decimal('kritik_stok', 12, 2)->default(0);
            $table->decimal('mevcut_stok', 12, 2)->default(0);
            $table->string('gorsel')->nullable();
            $table->text('aciklama')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urunler');
    }
};
