<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('status', 20)->default('active');
            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token', 64)->nullable()->unique();
            $table->timestamp('email_verification_sent_at')->nullable();
            // Brute-force protection
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            // Password management
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            // Session tracking
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->string('last_login_user_agent')->nullable();
            // 2FA
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index('email');
            $table->index('status');
            $table->index(['locked_until', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
