<?php declare(strict_types=1);
namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) return response()->json(['status'=>'error','message'=>'Unauthenticated.','code'=>'UNAUTHENTICATED'],401);
        if (!$user->hasAnyRole($roles)) {
            SecurityAuditLog::record(SecurityAuditLog::EVENT_PERMISSION_DENIED,'blocked',['required_roles'=>$roles,'user_role'=>$user->getRoleNames()->first(),'path'=>$request->path()],null,$user->id);
            return response()->json(['status'=>'error','message'=>'Insufficient role. Required: '.implode(' or ',$roles),'code'=>'INSUFFICIENT_ROLE'],403);
        }
        return $next($request);
    }
}
