<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * تسجيل دخول المستخدم إلى النظام والتحقق من صلاحية الدور المطلوب
     */
    public function login(Request $request)
    {
        // 1. التحقق من صحة المدخلات القادمة من الواجهة
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'required|string', // الدور المختار من نموذج تسجيل الدخول
        ]);

        // 2. البحث عن المستخدم بواسطة البريد الإلكتروني
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة!'], 401);
        }

        $chosenRole = strtolower($request->role);
        $hasRole = $user->roles()->where('name', $chosenRole)->exists();

        // ترجمة اسم الدور للغة العربية لرسائل الخطأ
        $rolesArabic = [
            'doctor' => 'طبيب',
            'manager' => 'مدير',
            'receptionist' => 'استقبال',
            'patient' => 'مريض',
        ];

        $roleArabic = $rolesArabic[$chosenRole] ?? '';

        if (! $hasRole) {
            return response()->json([
                'message' => 'عذراً، حسابك لا يمتلك صلاحية الدخول بصفة '.$roleArabic.'!',
            ], 422);
        }

        $roleName = ucfirst($chosenRole);
        
        // 3. تسجيل الدخول وتخزين البيانات في الجلسة 
        Auth::login($user);
        session([
            'user_id' => $user->id,
            'user_role' => $roleName,
        ]);

        return response()->json([
            'status' => 'success',
            'user_type' => $roleName,
            'user_name' => $user->name,
        ], 200);
    }

    /**
     * تسجيل حساب جديد أو إضافة دور جديد لمستخدم مسجل مسبقاً
     */
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'phone' => 'required|string',
            'role' => 'required|string',
            'gender' => 'required_if:role,patient|in:male,female',
        ]);

        try {
            $role = Role::where('name', $validatedData['role'])->first();

            if (! $role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'عذراً، الدور المحدد غير موجود في النظام!',
                ], 400);
            }

            $user = User::where('email', $validatedData['email'])->first();

            if ($user) {
                // إذا كان المستخدم موجوداً، تحقق مما إذا كان يمتلك الدور مسبقاً
                if ($user->roles()->where('role_id', $role->id)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'هذا الحساب مسجل بالفعل بهذا الدور!',
                    ], 422);
                }

                $user->roles()->attach($role->id);
                $message = 'تم إضافة الصلاحية الجديدة لحسابك الحالي بنجاح!';
            } else {
                // إنشاء مستخدم جديد كلياً
                $user = User::create([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                    'phone' => $validatedData['phone'],
                ]);

                $user->roles()->attach($role->id);
                $message = 'تم إنشاء حسابكِ بنجاح لأول مرة!';
            }

            // إنشاء سجل مريض  إذا كان الدور مريضاً ولم يكن له ملف مسبق
            if ($validatedData['role'] === 'patient' && ! $user->patient()->exists()) {
                $user->patient()->create([
                    'name' => $user->name,
                    'phone' => $validatedData['phone'],
                    'gender' => $validatedData['gender'],
                ]);
            }

            Auth::login($user);
            $currentRoleName = ucfirst(strtolower($validatedData['role']));

            session([
                'user_id' => $user->id,
                'user_role' => $currentRoleName, // حفظ الدور الحالي الفعّال في الجلسة
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'user_name' => $user->name,
                'current_role' => $currentRoleName,
                'all_roles' => $user->roles()->pluck('name')->toArray(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ غير متوقع أثناء المعالجة.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تسجيل الخروج وإنهاء الجلسة بالكامل
     */
    public function logout(Request $request)
    {
        Auth::logout();

        // إلغاء الجلسة بالكامل وحذف كافة البيانات المخزنة فيها
        $request->session()->invalidate();


        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج بنجاح وانتهت الجلسة.',
        ], 200);
    }
}