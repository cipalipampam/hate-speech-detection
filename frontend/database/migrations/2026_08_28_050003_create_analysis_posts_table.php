<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: analysis_posts
 *
 * Tanggung Jawab Tunggal: Menyimpan METADATA POSTINGAN MEDIA SOSIAL.
 * Data ini bersifat statis (tidak berubah setelah di-insert).
 * Tidak menyimpan teks konten (ada di analysis_classifications).
 *
 * Mengapa dipisah dari analysis_classifications?
 * → Jika ingin mencari "postingan dari user @fulan" atau "postingan dari platform X",
 *   kita query tabel ini saja tanpa membawa beban kolom longtext konten teks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('analyses')->onDelete('cascade')->index();
            $table->string('platform', 20)->index();
            $table->text('source_url')->nullable();
            $table->string('author_username', 100)->nullable()->index();
            $table->string('post_type', 50)->default('Original Post');
            $table->string('post_date', 50)->nullable();
            $table->timestamps();
            $table->index(['analysis_id', 'platform']);
            $table->index(['analysis_id', 'author_username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_posts');
    }
};
