<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: analysis_statistics
 *
 * Tanggung Jawab Tunggal: Menyimpan RINGKASAN METRIK AGREGASI per sesi analisis.
 * Satu baris per satu sesi analisis (one-to-one dengan analyses).
 * Di-insert/update hanya saat analisis berstatus 'completed'.
 *
 * Mengapa dipisah dari analyses?
 * → Saat polling status job (bisa ratusan kali), tabel analyses selalu diquery
 *   dan sangat cepat karena tidak ada kolom angka/JSON besar di dalamnya.
 * → Dashboard hanya perlu JOIN ke tabel ini, bukan ke tabel records yang besar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->unique()->constrained('analyses')->onDelete('cascade');
            $table->unsignedInteger('total_data')->default(0);
            $table->unsignedInteger('hate_speech_count')->default(0);
            $table->unsignedInteger('non_hate_speech_count')->default(0);
            $table->decimal('hate_speech_pct', 5, 2)->default(0.00);
            $table->decimal('avg_confidence_lvl1', 6, 2)->default(0.00);
            $table->decimal('avg_confidence_lvl2', 6, 2)->default(0.00);
            $table->json('level2_breakdown')->nullable();
            $table->json('platform_breakdown')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_statistics');
    }
};
