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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->onDelete('cascade');
            $table->integer('unit_number')->default(1);
            $table->string('title'); // e.g. الوحدة 1: الاتزان والتأسيس
            $table->string('subtitle')->nullable(); // e.g. 4 محاضرات • 3 ساعات
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
