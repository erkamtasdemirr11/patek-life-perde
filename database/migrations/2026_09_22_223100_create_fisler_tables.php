<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yıl bazlı sayaç: fiş numarası buradan lockForUpdate ile alınır.
        Schema::create('fis_numaralari', function (Blueprint $table) {
            $table->unsignedSmallInteger('yil')->primary();
            $table->unsignedInteger('son_no')->default(0);
        });

        Schema::create('fisler', function (Blueprint $table) {
            $table->id();
            $table->string('fis_no', 12)->unique();
            $table->foreignId('musteri_id')->constrained('musteriler')->restrictOnDelete();
            $table->dateTime('tarih');
            $table->decimal('ara_toplam', 12, 2);
            $table->decimal('iskonto', 12, 2)->default(0);
            $table->decimal('genel_toplam', 12, 2);
            $table->string('durum', 10)->default('aktif');   // App\Enums\FisDurumu
            $table->string('aciklama')->nullable();
            $table->string('iptal_nedeni')->nullable();
            $table->foreignId('iptal_eden_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('iptal_tarihi')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tarih');
            $table->index(['musteri_id', 'tarih']);
        });

        Schema::create('fis_kalemleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fis_id')->constrained('fisler')->restrictOnDelete();
            $table->foreignId('urun_id')->constrained('urunler')->restrictOnDelete();
            $table->decimal('miktar', 12, 2);
            $table->decimal('birim_fiyat', 12, 2);
            $table->decimal('tutar', 12, 2);
            $table->timestamps();
        });

        // Aşama 4'te ertelenen FK: stok hareketi → fiş
        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->foreign('fis_id')->references('id')->on('fisler')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->dropForeign(['fis_id']);
        });
        Schema::dropIfExists('fis_kalemleri');
        Schema::dropIfExists('fisler');
        Schema::dropIfExists('fis_numaralari');
    }
};
