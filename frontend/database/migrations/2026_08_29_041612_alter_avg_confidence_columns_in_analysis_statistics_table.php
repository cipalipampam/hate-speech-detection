<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analysis_statistics', function (Blueprint $table) {
            $table->decimal('avg_confidence_lvl1', 6, 2)->default(0.00)->change();
            $table->decimal('avg_confidence_lvl2', 6, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_statistics', function (Blueprint $table) {
            $table->decimal('avg_confidence_lvl1', 5, 4)->default(0.0000)->change();
            $table->decimal('avg_confidence_lvl2', 5, 4)->default(0.0000)->change();
        });
    }
};
