<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Açık hesap / peşin ayrımı: fişe ödeme tipi, tahsilata fiş bağlantısı eklendi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fisler', function (Blueprint $table) {
            $table->string('odeme_tipi', 20)->default('acik_hesap')->after('genel_toplam');   // App\Enums\OdemeTipi
        });

        Schema::table('tahsilatlar', function (Blueprint $table) {
            // Fişle birlikte kesilen peşin tahsilat; elle girilen tahsilatta boş kalır.
            $table->foreignId('fis_id')->nullable()->after('musteri_id')->constrained('fisler')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tahsilatlar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fis_id');
        });

        Schema::table('fisler', function (Blueprint $table) {
            $table->dropColumn('odeme_tipi');
        });
    }
};
