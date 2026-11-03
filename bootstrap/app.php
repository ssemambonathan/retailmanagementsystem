<?php declare(strict_types=1);

use App\Http\Middleware\{EnsureEmailVerifiedMiddleware, ForceJsonMiddleware, JwtAuthMiddleware, PermissionMiddleware, RoleMiddleware, SecurityHeadersMiddleware};
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, MethodNotAllowedHttpException, NotFoundHttpException, TooManyRequestsHttpException};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', apiPrefix: 'api')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ForceJsonMiddleware::class, SecurityHeadersMiddleware::class]);
        $middleware->alias([
            'jwt.auth'       => JwtAuthMiddleware::class,
            'role'           => RoleMiddleware::class,
            'permission'     => PermissionMiddleware::class,
            'email.verified' => EnsureEmailVerifiedMiddleware::class,
        ]);
        RateLimiter::for('auth',     fn (Request $r): Limit => Limit::perMinute(10)->by($r->ip())->response(fn ($req,$hdrs) => response()->json(['status'=>'error','message'=>'Too many attempts.','code'=>'RATE_LIMITED','retry_after'=>$hdrs['Retry-After']??60],429,$hdrs)));
        RateLimiter::for('password', fn (Request $r): Limit => Limit::perMinute(3)->by($r->ip()));
        RateLimiter::for('refresh',  fn (Request $r): Limit => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('api',      fn (Request $r): Limit => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ValidationException $e, Request $r) => response()->json(['status'=>'error','message'=>'The given data was invalid.','code'=>'VALIDATION_FAILED','errors'=>$e->errors()],422));
        $exceptions->render(fn (AuthenticationException $e, Request $r) => response()->json(['status'=>'error','message'=>$e->getMessage()?:'Authentication required.','code'=>'UNAUTHENTICATED'],401));
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $r) => response()->json(['status'=>'error','message'=>'You do not have permission to perform this action.','code'=>'FORBIDDEN'],403));
        $exceptions->render(fn (ModelNotFoundException $e, Request $r) => response()->json(['status'=>'error','message'=>class_basename($e->getModel()).' not found.','code'=>'NOT_FOUND'],404));
        $exceptions->render(fn (NotFoundHttpException $e, Request $r) => response()->json(['status'=>'error','message'=>'Endpoint not found.','code'=>'ENDPOINT_NOT_FOUND'],404));
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $r) => response()->json(['status'=>'error','message'=>'HTTP method not allowed.','code'=>'METHOD_NOT_ALLOWED'],405));
        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $r) => response()->json(['status'=>'error','message'=>'Too many requests.','code'=>'RATE_LIMITED','retry_after'=>$e->getHeaders()['Retry-After']??60],429));
        $exceptions->render(function (\Throwable $e, Request $r) {
            if (app()->environment('production')) {
                \Illuminate\Support\Facades\Log::error('Unhandled exception',['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'user_id'=>optional($r->user())->id]);
                return response()->json(['status'=>'error','message'=>'An unexpected error occurred.','code'=>'INTERNAL_SERVER_ERROR'],500);
            }
            return null;
        });
    })->create();
