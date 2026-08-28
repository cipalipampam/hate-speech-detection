<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: analyses
 *
 * Tanggung Jawab Tunggal: Menyimpan KONFIGURASI & STATUS JOB analisis saja.
 *
 * Kolom yang dipindahkan ke tabel terpisah:
 * - Metrik agregasi sentimen → analysis_statistics
 * - Metadata file ekspor     → analysis_exports
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('job_id')->nullable()->index();
            $table->string('title');
            $table->enum('platform', ['x', 'threads', 'both'])->default('both');
            $table->json('keywords');
            $table->enum('search_mode', ['latest', 'top'])->default('latest');
            $table->unsignedSmallInteger('max_links')->default(50);
            $table->unsignedSmallInteger('max_scroll_steps')->default(300);
            $table->boolean('headless')->default(false);
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued')->index();
            $table->decimal('execution_time_seconds', 8, 2)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
