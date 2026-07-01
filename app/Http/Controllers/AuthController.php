<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
public function login(Request $request) {
    // return response()->json($request->all());
    $user = User::where('email', $request->email)->first();
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'بيانات الدخول غير صحيحة!'], 401);
    }

    if ($user->roles->isEmpty()) {
        return response()->json(['message' => 'هذا الحساب غير مرتبط بصلاحية!'], 422);
    }

    $roleName = ucfirst($user->roles->first()->name);

    session([
        'user_id'   => $user->id,
        'user_role' => $roleName
    ]);

    return response()->json([
        'status'    => 'success',
        'user_type' => $roleName,
        'user_name' => $user->name 
    ]);
}

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'required|string',
            'role' => 'required|string',
            'gender' => 'required_if:role,patient|in:male,female',
        ]);

        DB::beginTransaction();

        try {
            $role = Role::where('name', $validatedData['role'])->first();

            if (! $role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'عذراً، الدور المحدد غير موجود في النظام!',
                ], 400);
            }

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'],
            ]);

            $user->roles()->attach($role->id);

            if ($validatedData['role'] === 'patient') {
                $user->patient()->create([
                    'name' => $user->name,
                    'phone' => $validatedData['phone'],
                    'gender' => $validatedData['gender'],
                ]);
            }

            DB::commit();

            // Auth::login($user);

            // $user->load('roles');
            // $firstRole = $user->roles->first();
            Auth::login($user);
            $user->load('roles');
            $firstRole=$user->roles->first();
            $roleName= $firstRole ? ucfirst($firstRole->name):null;

            session([
                'user_id' => $user->id,
                'user_role' => $roleName
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'تم إنشاء حسابكِ بنجاح ',
                'user_name' => $user->name,
                'user_type' => $firstRole ? $firstRole->name : null,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ غير متوقع أثناء إنشاء الحساب، يرجى المحاولة لاحقاً.',
                'error' => $e->getMessage(), 
            ], 500);
        }
    }
}
