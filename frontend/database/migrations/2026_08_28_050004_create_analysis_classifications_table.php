<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: analysis_classifications
 *
 * Tanggung Jawab Tunggal: Menyimpan ISI TEKS & HASIL KLASIFIKASI IndoBERT.
 * One-to-one dengan analysis_posts (setiap postingan memiliki tepat 1 hasil klasifikasi).
 *
 * Mengapa dipisah dari analysis_posts?
 * → Kolom longtext (raw_content, clean_content) sangat berat dan tidak diperlukan
 *   saat hanya mengambil metadata postingan (platform, author, URL, dsb).
 * → Tabel ini bisa di-query secara efisien hanya saat filter label/confidence diperlukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained('analysis_posts')->onDelete('cascade');
            $table->longText('raw_content');
            $table->longText('clean_content');
            $table->string('label_lvl1', 50)->index();
            $table->decimal('confidence_lvl1', 6, 4)->default(0.0000);
            $table->json('probabilities_lvl1')->nullable();
            $table->string('label_lvl2', 80)->index();
            $table->decimal('confidence_lvl2', 6, 4)->default(0.0000);
            $table->json('probabilities_lvl2')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_classifications');
    }
};
