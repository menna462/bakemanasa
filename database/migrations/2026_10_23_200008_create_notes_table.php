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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->foreignId('activation_code_id')->nullable()->constrained('activation_codes')->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->string('time_formatted')->default('00:00'); // e.g. "15:20"
            $table->unsignedInteger('time_seconds')->default(0); // e.g. 920
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
