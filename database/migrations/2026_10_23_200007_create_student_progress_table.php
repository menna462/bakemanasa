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
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_code_id')->nullable()->constrained('activation_codes')->nullOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->string('student_name'); // e.g. "زياد تامر السعيد"
            $table->string('student_phone')->nullable();
            $table->string('grade')->nullable(); // e.g. "الثالث الثانوي"
            $table->unsignedTinyInteger('watch_progress')->default(0); // 0 to 100%
            $table->unsignedInteger('current_seconds')->default(0); // e.g. 1455
            $table->unsignedInteger('total_watch_minutes')->default(0);
            $table->boolean('completed')->default(false);
            $table->unsignedTinyInteger('quiz_score')->nullable(); // e.g. 95
            $table->unsignedInteger('replay_count')->default(1);
            $table->enum('device', ['mobile', 'desktop', 'tablet'])->default('desktop');
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'student_phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_progress');
    }
};
