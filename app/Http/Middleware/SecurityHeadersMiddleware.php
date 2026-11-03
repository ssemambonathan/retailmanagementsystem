<?php declare(strict_types=1);
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response  = $next($request);
        $requestId = $request->header('X-Request-ID', \Illuminate\Support\Str::uuid()->toString());
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','DENY');
        $response->headers->set('X-XSS-Protection','1; mode=block');
        $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy',"default-src 'none'; frame-ancestors 'none'");
        $response->headers->set('X-Request-ID',$requestId);
        if ($request->secure()||app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security','max-age=31536000; includeSubDomains; preload');
        }
        if (str_starts_with($request->path(),'api/v1/auth/')) {
            $response->headers->set('Cache-Control','no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma','no-cache');
        }
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        return $response;
    }
}
