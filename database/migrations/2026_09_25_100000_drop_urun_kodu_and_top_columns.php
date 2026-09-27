<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sadeleştirme: ürün kodu kaldırıldı (ürünler yalnızca adıyla tanınır),
// top adedi x top metresi girişi kaldırıldı (her giriş doğrudan miktar olarak yapılır).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urunler', function (Blueprint $table) {
            $table->dropUnique(['urun_kodu']);
            $table->dropColumn('urun_kodu');
        });

        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->dropColumn(['top_adedi', 'top_metresi']);
        });
    }

    public function down(): void
    {
        Schema::table('urunler', function (Blueprint $table) {
            $table->string('urun_kodu', 50)->nullable()->unique()->after('id');
        });

        Schema::table('stok_hareketleri', function (Blueprint $table) {
            $table->unsignedInteger('top_adedi')->nullable()->after('miktar');
            $table->decimal('top_metresi', 10, 2)->nullable()->after('top_adedi');
        });
    }
};
