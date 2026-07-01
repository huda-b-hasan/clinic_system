<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    // بداخل Middleware/CheckAuth.php

    // بداخل app/Http/Middleware/CheckAuth.php
public function handle(Request $request, Closure $next)
{
    if (!session()->has('user_id')) {
        return response()->json(['message' => 'غير مصرح لك، يرجى تسجيل الدخول'], 401);
    }

    return $next($request);
}
}
