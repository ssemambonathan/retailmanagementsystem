<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->string('token_hash', 64)->unique()->comment('SHA-256 of raw token');
            $table->uuid('family_id')->comment('Reuse-detection: all tokens from same login share family_id');
            $table->boolean('is_used')->default(false);
            $table->boolean('is_revoked')->default(false);
            $table->string('device_name', 100)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->ipAddress('created_from_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 50)->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_revoked']);
            $table->index('family_id');
            $table->index('expires_at');
        });
    }
    public function down(): void { Schema::dropIfExists('refresh_tokens'); }
};
