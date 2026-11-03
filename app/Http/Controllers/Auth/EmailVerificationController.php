<?php declare(strict_types=1);
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\BaseApiController;
use App\Services\AuthService;
use Illuminate\Http\{JsonResponse, Request};

final class EmailVerificationController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService) {}

    public function verify(string $userId, string $token): JsonResponse
    {
        $this->authService->verifyEmail($userId, $token);
        return $this->success(message: 'Email verified successfully. You may now log in.');
    }

    public function resend(Request $request): JsonResponse
    {
        $this->authService->resendVerificationEmail($request->user());
        return $this->success(message: 'Verification email sent. Please check your inbox.');
    }
}
