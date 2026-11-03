<?php declare(strict_types=1);
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class EnsureEmailVerifiedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->hasVerifiedEmail()) {
            return response()->json(['status'=>'error','message'=>'Your email address is not verified.','code'=>'EMAIL_NOT_VERIFIED'],403);
        }
        return $next($request);
    }
}
