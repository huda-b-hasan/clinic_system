<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. التحقق من المدخلات القادمة من نموذج الـ HTML
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'role' => 'required|string', // الدور الذي اختاره المستخدم من الواجهة
        ]);

        // 2. البحث عن المستخدم بواسطة البريد الإلكتروني
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة!'], 401);
        }

        $chosenRole = strtolower($request->role);

        $hasRole = $user->roles()->where('name', $chosenRole)->exists();
        if($request->role==="Doctor"){
            $roleArabic="طبيب";
        }
        else if($request->role==="Manager"){
            $roleArabic="مدير";

        }
        else if($request->role==="Receptionist"){
            $roleArabic="استقبال";

        }
        else if($request->role==="Patient"){
            $roleArabic="مريض";

        }

        if (! $hasRole) {
            return response()->json([
                'message' => 'عذراً، حسابك لا يمتلك صلاحية الدخول بصفة '.$roleArabic.'!',
            ], 422);
        }

        $roleName = ucfirst($chosenRole);
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

    // public function register(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|string|email|max:255|unique:users',
    //         'password' => 'required|string|min:8',
    //         'phone' => 'required|string',
    //         'role' => 'required|string',
    //         'gender' => 'required_if:role,patient|in:male,female',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $role = Role::where('name', $validatedData['role'])->first();

    //         if (! $role) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'عذراً، الدور المحدد غير موجود في النظام!',
    //             ], 400);
    //         }

    //         $user = User::create([
    //             'name' => $validatedData['name'],
    //             'email' => $validatedData['email'],
    //             'password' => Hash::make($validatedData['password']),
    //             'phone' => $validatedData['phone'],
    //         ]);

    //         $user->roles()->attach($role->id);

    //         if ($validatedData['role'] === 'patient') {
    //             $user->patient()->create([
    //                 'name' => $user->name,
    //                 'phone' => $validatedData['phone'],
    //                 'gender' => $validatedData['gender'],
    //             ]);
    //         }

    //         DB::commit();

    //         // Auth::login($user);

    //         // $user->load('roles');
    //         // $firstRole = $user->roles->first();
    //         Auth::login($user);
    //         $user->load('roles');
    //         $firstRole=$user->roles->first();
    //         $roleName= $firstRole ? ucfirst($firstRole->name):null;

    //         session([
    //             'user_id' => $user->id,
    //             'user_role' => $roleName
    //         ]);
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'تم إنشاء حسابكِ بنجاح ',
    //             'user_name' => $user->name,
    //             'user_type' => $firstRole ? $firstRole->name : null,
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'حدث خطأ غير متوقع أثناء إنشاء الحساب، يرجى المحاولة لاحقاً.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
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

                if ($user->roles()->where('role_id', $role->id)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'هذا الحساب مسجل بالفعل بهذا الدور!',
                    ], 422);
                }

                $user->roles()->attach($role->id);
                $message = 'تم إضافة الصلاحية الجديدة لحسابك الحالي بنجاح!';
            } else {
                $user = User::create([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                    'phone' => $validatedData['phone'],
                ]);

                $user->roles()->attach($role->id);
                $message = 'تم إنشاء حسابكِ بنجاح لأول مرة!';
            }

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
}
