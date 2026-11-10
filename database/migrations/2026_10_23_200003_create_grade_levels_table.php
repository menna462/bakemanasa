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
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // e.g. الصف الثالث الثانوي
            $table->string('subtitle')->nullable(); // e.g. دفعة الثانوية العامة 2025/2026
            $table->integer('order_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('status_text')->default('نشط حالياً');
            $table->enum('status_type', ['active', 'published'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
