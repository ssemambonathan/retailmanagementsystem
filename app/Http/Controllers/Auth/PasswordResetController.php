<?php declare(strict_types=1);
namespace App\Http\Controllers\Auth;
use App\DTOs\Auth\ResetPasswordDTO;
use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Auth\{ChangePasswordRequest, ForgotPasswordRequest, ResetPasswordRequest};
use App\Services\AuthService;
use Illuminate\Http\{JsonResponse, Request};

final class PasswordResetController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->requestPasswordReset($request->string('email')->lower()->toString(), $request->ip());
        return $this->success(message: 'If that email is registered, a reset link has been sent.');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(ResetPasswordDTO::fromRequest($request), $request->ip());
        return $this->success(message: 'Password reset successfully. Please log in with your new password.');
    }

    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->string('current_password')->toString(), $request->string('password')->toString(), $request->bearerToken());
        return $this->success(message: 'Password changed successfully. Please log in again.');
    }
}
