<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds unit_id to activation_codes so a code can be assigned to a full unit
     * (covering all its lessons) instead of a single lesson.
     */
    public function up(): void
    {
        Schema::table('activation_codes', function (Blueprint $table) {
            $table->foreignId('unit_id')
                  ->nullable()
                  ->after('lesson_id')
                  ->constrained('units')
                  ->nullOnDelete();

            $table->index('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activation_codes', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
