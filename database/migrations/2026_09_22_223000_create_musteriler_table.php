<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('musteriler', function (Blueprint $table) {
            $table->id();
            $table->string('firma_adi');
            $table->string('yetkili')->nullable();
            $table->string('telefon', 30)->nullable();
            $table->text('adres')->nullable();
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musteriler');
    }
};
