<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAnyRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()?->hasAnyRole($roles)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'FORBIDDEN',
                        'message' => 'This action requires an authorized role.',
                    ],
                ], 403);
            }

            abort(403, __('This action requires an authorized role.'));
        }

        return $next($request);
    }
}
