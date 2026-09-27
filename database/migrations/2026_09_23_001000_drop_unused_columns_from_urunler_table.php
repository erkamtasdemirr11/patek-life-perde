<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ürün kartı sadeleştirildi: desen, en ve varsayılan top metresi kaldırıldı.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urunler', function (Blueprint $table) {
            $table->dropColumn(['desen', 'en_cm', 'varsayilan_top_metresi']);
        });
    }

    public function down(): void
    {
        Schema::table('urunler', function (Blueprint $table) {
            $table->string('desen', 100)->nullable()->after('renk');
            $table->unsignedSmallInteger('en_cm')->nullable()->after('desen');
            $table->decimal('varsayilan_top_metresi', 10, 2)->nullable()->after('birim');
        });
    }
};
