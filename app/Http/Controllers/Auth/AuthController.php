<?php declare(strict_types=1);
namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\{LoginDTO, RegisterDTO};
use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Auth\{LoginRequest, RefreshTokenRequest, RegisterRequest};
use App\Services\{AuthService, JwtService};
use Illuminate\Http\{JsonResponse, Request};

final class AuthController extends BaseApiController
{
    public function __construct(private readonly AuthService $authService, private readonly JwtService $jwtService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(LoginDTO::fromRequest($request), $request);
        return $this->success($result, 'Login successful');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register(RegisterDTO::fromRequest($request));
        return $this->created([
            'user'    => ['id'=>$user->id,'full_name'=>$user->full_name,'email'=>$user->email,'role'=>$user->getRoleNames()->first()],
            'message' => 'Please verify your email address.',
        ], 'Account created');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), $request->bearerToken(), $request->input('refresh_token'));
        return $this->success(message: 'Logged out successfully');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user(), $request->bearerToken());
        return $this->success(message: 'Logged out from all devices');
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->jwtService->rotateRefreshToken($request->string('refresh_token')->toString(), $request);
        return $this->success($tokens, 'Token refreshed');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');
        return $this->success([
            'id'             => $user->id,
            'full_name'      => $user->full_name,
            'first_name'     => $user->first_name,
            'last_name'      => $user->last_name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'status'         => $user->status,
            'email_verified' => $user->hasVerifiedEmail(),
            'role'           => $user->getRoleNames()->first(),
            'permissions'    => $user->getAllPermissions()->pluck('name'),
            'last_login_at'  => $user->last_login_at?->toIso8601String(),
        ]);
    }
}
