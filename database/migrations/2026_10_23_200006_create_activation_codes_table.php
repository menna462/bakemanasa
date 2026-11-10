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
        Schema::create('activation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. "AH-8923-X1"
            $table->string('group_name'); // e.g. "سنتر الأمل — شهر 10"
            $table->foreignId('grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('validity_days')->default(30);
            $table->enum('status', ['new', 'used', 'expired'])->default('new');
            $table->string('used_by')->nullable(); // e.g. "محمد كريم (سنتر النور)"
            $table->string('student_phone')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Indexes for fast code search and filtering
            $table->index(['status', 'group_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activation_codes');
    }
};
