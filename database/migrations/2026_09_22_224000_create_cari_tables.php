<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('musteriler', function (Blueprint $table) {
            $table->decimal('acilis_bakiyesi', 14, 2)->default(0)->after('notlar');
            // Yalnızca CariServisi yazar; pozitif = müşteri bize borçlu.
            $table->decimal('bakiye', 14, 2)->default(0)->after('acilis_bakiyesi');
        });

        Schema::create('makbuz_numaralari', function (Blueprint $table) {
            $table->unsignedSmallInteger('yil')->primary();
            $table->unsignedInteger('son_no')->default(0);
        });

        Schema::create('tahsilatlar', function (Blueprint $table) {
            $table->id();
            $table->string('makbuz_no', 12)->unique();
            $table->foreignId('musteri_id')->constrained('musteriler')->restrictOnDelete();
            $table->dateTime('tarih');
            $table->decimal('tutar', 12, 2);
            $table->string('odeme_yontemi', 20);   // App\Enums\OdemeYontemi
            $table->string('durum', 10)->default('aktif');   // App\Enums\TahsilatDurumu
            $table->string('aciklama')->nullable();
            $table->string('iptal_nedeni')->nullable();
            $table->foreignId('iptal_eden_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('iptal_tarihi')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['musteri_id', 'tarih']);
            $table->index('tarih');
        });

        Schema::create('cari_hareketler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('musteri_id')->constrained('musteriler')->restrictOnDelete();
            $table->dateTime('tarih');
            $table->string('tip', 10);      // App\Enums\CariTipi
            $table->string('kaynak', 20);   // App\Enums\CariKaynagi
            $table->decimal('tutar', 12, 2);
            $table->decimal('bakiye_sonrasi', 14, 2);
            $table->foreignId('fis_id')->nullable()->constrained('fisler')->restrictOnDelete();
            $table->foreignId('tahsilat_id')->nullable()->constrained('tahsilatlar')->restrictOnDelete();
            $table->string('aciklama')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['musteri_id', 'tarih']);
            $table->index('tarih');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cari_hareketler');
        Schema::dropIfExists('tahsilatlar');
        Schema::dropIfExists('makbuz_numaralari');
        Schema::table('musteriler', function (Blueprint $table) {
            $table->dropColumn(['acilis_bakiyesi', 'bakiye']);
        });
    }
};
