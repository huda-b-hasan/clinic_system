<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Models\User;
use Closure;
class CheckAuth
{
    /**
      //  * Handle an incoming request.
      //  *
      //  */
    // // بداخل Middleware/CheckAuth.php

    // // بداخل app/Http/Middleware/CheckAuth.php
    // public function handle(Request $request, Closure $next)
    // {
    //     if (! session()->has('user_id')) {
    //         return response()->json(['message' => 'غير مصرح لك، يرجى تسجيل الدخول'], 401);
    //     }

    //     return $next($request);
    // }

    public function handle(Request $request, Closure $next, ?string $role = null)
    {
        // 1. التحقق الأساسي المشترك لجميع الروابط (تسجيل الدخول)
        if (! session()->has('user_id')) {
            return response()->json(['message' => 'غير مصرح لك، يرجى تسجيل الدخول'], 401);
        }

        // 2. إذا كان الراوت مستدعى بدون تحديد دور (مثل كل الروابط الحالية عندك)
        // سيمر الطلب بسلام تماماً مثل كودك القديم
        if ($role === null) {
            return $next($request);
        }

        // 3. التحقق الخاص بالصلاحيات (عندما نطلب دور محدد مثل doctor)
        $user = User::with('roles')->find(session('user_id'));

        if (! $user || ! $user->roles->contains('name', $role)) {
            return response()->json(['message' => 'عذراً، لا تمتلك الصلاحية للوصول لهذه الصفحة'], 403);
        }

        return $next($request);
    }
}
