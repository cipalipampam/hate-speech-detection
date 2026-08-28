<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: single_predictions
 *
 * Tanggung Jawab Tunggal: Menyimpan riwayat UJI COBA TEKS MANUAL oleh pengguna.
 * Mandiri, tidak bergantung pada sesi analisis penuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('single_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->text('input_text');
            $table->text('clean_text')->nullable();
            $table->string('label_lvl1', 50)->index();
            $table->string('label_lvl2', 80)->index();
            $table->decimal('confidence_lvl1', 6, 4)->default(0.0000);
            $table->decimal('confidence_lvl2', 6, 4)->default(0.0000);
            $table->json('probabilities_lvl1')->nullable();
            $table->json('probabilities_lvl2')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('single_predictions');
    }
};
