<?php declare(strict_types=1);
namespace App\Http\Middleware;

use App\Exceptions\{AccountLockedException,EmailNotVerifiedException,TokenRevokedException};
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\{JWTException,TokenExpiredException,TokenInvalidException};

class JwtAuthMiddleware
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) return $this->json('Token not provided.','TOKEN_ABSENT',401);
        try {
            $user = $this->jwtService->validateAccessToken($token);
        } catch (TokenExpiredException)      { return $this->json('Token has expired.','TOKEN_EXPIRED',401);
        } catch (TokenInvalidException)      { return $this->json('Token is invalid.','TOKEN_INVALID',401);
        } catch (TokenRevokedException $e)   { return $this->json($e->getMessage(),'TOKEN_REVOKED',401);
        } catch (EmailNotVerifiedException $e){ return $this->json($e->getMessage(),'EMAIL_NOT_VERIFIED',403);
        } catch (AccountLockedException $e)  { return $this->json($e->getMessage(),'ACCOUNT_LOCKED',423);
        } catch (JWTException)               { return $this->json('Token processing failed.','TOKEN_ERROR',401); }
        auth('api')->setUser($user);
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
    private function json(string $msg, string $code, int $status): Response
    {
        return response()->json(['status'=>'error','message'=>$msg,'code'=>$code],$status);
    }
}
