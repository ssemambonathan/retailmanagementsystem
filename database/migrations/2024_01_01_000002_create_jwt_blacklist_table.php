<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jwt_blacklist', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('jti', 64)->unique()->comment('JWT ID claim');
            $table->uuid('user_id')->nullable();
            $table->string('token_type', 20)->default('access');
            $table->string('reason', 50)->default('logout');
            $table->uuid('revoked_by')->nullable();
            $table->ipAddress('revoked_from_ip')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('jti');
            $table->index('expires_at');
            $table->index(['user_id', 'revoked_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('jwt_blacklist'); }
};
