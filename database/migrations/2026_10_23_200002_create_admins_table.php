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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('short_title')->nullable()->default('أ/'); // e.g. "م/" "أ/" "د/"
            $table->string('initials')->nullable(); // e.g. "أح"
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'code_manager', 'content_manager'])->default('content_manager');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->string('avatar_color')->default('#FF6A00');
            $table->boolean('is_founder')->default(false);
            $table->string('last_activity')->nullable()->default('الآن');
            $table->enum('last_activity_type', ['online', 'today', 'days_ago', 'weeks_ago'])->default('today');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_roles', function (Blueprint $table) {
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->primary(['admin_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('admins');
    }
};
