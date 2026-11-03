<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->string('token_hash', 64)->unique()->comment('SHA-256 of raw token');
            $table->boolean('is_used')->default(false);
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->ipAddress('requested_from_ip')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->ipAddress('used_from_ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'is_used']);
            $table->index('expires_at');
        });
    }
    public function down(): void { Schema::dropIfExists('password_reset_tokens'); }
};
