<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use App\Models\User;
use Closure;

class CheckAuth
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 1. التحقق من تسجيل الدخول
        if (! session()->has('user_id')) {
            return response()->json(['message' => 'غير مصرح لك، يرجى تسجيل الدخول'], 401);
        }

        // 2. إذا لم يحدد أي دور للميدل وير، يمر الطلب بسلام
        if (empty($roles)) {
            return $next($request);
        }

        // 3. جلب المستخدم مع أدوراه
        $user = User::with('roles')->find(session('user_id'));

        if (!$user) {
            return response()->json(['message' => 'عذراً، لا تمتلك الصلاحية للوصول لهذه الصفحة'], 403);
        }

        // 4. دمج وتفكيك الأدوار (تتعامل مع الفاصلة 'Doctor,Receptionist' أو التمرير متعدد المتغيرات)
        $parsedRoles = [];
        foreach ($roles as $role) {
            if ($role) {
                $parsedRoles = array_merge($parsedRoles, explode(',', $role));
            }
        }
        $parsedRoles = array_map('trim', $parsedRoles);

        // 5. التحقق مما إذا كان المستخدم يملك أي دور من الأدوار المسموحة
        $hasPermission = $user->roles->contains(function ($userRole) use ($parsedRoles) {
            return in_array($userRole->name, $parsedRoles);
        });

        if (!$hasPermission) {
            return response()->json(['message' => 'عذراً، لا تمتلك الصلاحية للوصول لهذه الصفحة'], 403);
        }

        return $next($request);
    }
}