<?php declare(strict_types=1);
namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (!$user) return response()->json(['status'=>'error','message'=>'Unauthenticated.','code'=>'UNAUTHENTICATED'],401);
        $has = false;
        foreach ($permissions as $p) { if ($user->can($p)) { $has = true; break; } }
        if (!$has) {
            SecurityAuditLog::record(SecurityAuditLog::EVENT_PERMISSION_DENIED,'blocked',['required_permissions'=>$permissions,'user_role'=>$user->getRoleNames()->first(),'path'=>$request->path()],null,$user->id);
            return response()->json(['status'=>'error','message'=>'You do not have permission to perform this action.','code'=>'INSUFFICIENT_PERMISSION'],403);
        }
        return $next($request);
    }
}
