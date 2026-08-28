<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: analysis_exports
 *
 * Tanggung Jawab Tunggal: Menyimpan METADATA FILE HASIL EKSPOR per sesi analisis.
 * Satu analisis bisa menghasilkan lebih dari satu file (CSV, Excel, PDF).
 *
 * Mengapa dipisah dari analyses?
 * → File ekspor bisa diregenerasi atau dihapus secara independen.
 * → Mudah menambah tipe format ekspor baru tanpa mengubah skema tabel analyses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained('analyses')->onDelete('cascade');
            $table->string('filename');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->enum('format', ['csv', 'xlsx', 'pdf'])->default('csv');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 80)->default('text/csv');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_exports');
    }
};
