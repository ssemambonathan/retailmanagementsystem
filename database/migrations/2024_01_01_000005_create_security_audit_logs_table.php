<?php declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('security_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id')->nullable();
            $table->string('event', 60);
            $table->string('result', 20)->default('success');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('country_code', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->string('risk_score', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('created_at');
            $table->index(['user_id', 'event', 'created_at']);
            $table->index(['ip_address', 'event']);
            $table->index(['event', 'result']);
        });
    }
    public function down(): void { Schema::dropIfExists('security_audit_logs'); }
};
