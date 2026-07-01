<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // التأكد من أن المستخدم مسجل دخوله ولديه الدور المطلوب
        if (!auth()->check() || !auth()->user()->hasRole($role)) {
            
            // إذا كان الطلب عبارة عن API (من الفيرست بيج أو فرونت اند مثلاً) نرجع استجابة JSON
            if ($request->expectsJson()) {
                return response()->json(['message' => 'عذراً، لا تملك الصلاحية للقيام بهذا الإجراء.'], 403);
            }
            
            // إذا كان طلب ويب عادي نرفع خطأ 403 (غير مصرح)
            abort(403, 'غير مصرح لك بالدخول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}