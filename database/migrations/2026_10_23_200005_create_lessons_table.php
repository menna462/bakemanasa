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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->foreignId('grade_level_id')->constrained('grade_levels')->onDelete('cascade');
            $table->integer('order_number')->default(1);
            $table->string('title'); // e.g. المحاضرة 1: التأسيس الشامل
            $table->text('description')->nullable();
            $table->string('duration')->default('00:00:00'); // e.g. "01:15:30"
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('size')->nullable(); // e.g. "450 MB"
            $table->unsignedBigInteger('views_count')->default(0);
            $table->enum('status', ['published', 'draft'])->default('published');
            $table->enum('video_source', ['upload', 'external'])->default('external');
            $table->text('video_url')->nullable();
            $table->enum('video_provider', ['youtube', 'bunny', 'cloudflare', 'vimeo', 'custom'])->default('bunny');
            $table->boolean('has_pdf')->default(false);
            $table->string('pdf_title')->nullable();
            $table->text('pdf_url')->nullable();
            $table->boolean('has_quiz')->default(false);
            $table->string('quiz_title')->nullable();
            $table->unsignedInteger('quiz_questions_count')->default(0);
            $table->string('quiz_duration')->nullable(); // e.g. "40 دقيقة"
            $table->boolean('is_free')->default(false); // If false, requires activation code
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
