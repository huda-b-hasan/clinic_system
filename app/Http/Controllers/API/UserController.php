<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * عرض قائمة الموظفين وإحصائياتهم مع إمكانية البحث والفلترة حسب الدور
     */
    public function index(Request $request)
    {
        // 1. حساب الإحصائيات (تستثني المرضى تلقائياً عبر Scopes)
        $stats = [
            'total_users' => User::staff()->count(),
            'doctors_count' => User::staff()->doctor()->count(),
            'reseptions_count' => User::staff()->receptionist()->count(),
        ];

        // 2. بناء الاستعلام الأساسي (جلب الموظفين مع أدوارهم)
        $query = User::staff()->with('roles');

        // البحث (بالاسم، البريد الإلكتروني، أو رقم الهاتف)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // الفلترة بحسب الدور الوظيفي
        if ($request->filled('role')) {
            $roleName = $request->role;
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        $users = $query->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->toArray(),
            ];
        });

        // إرجاع الاستجابة بصيغة JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => $stats,
                    'users' => $users,
                ],
            ], 200);
        }
    }

    /**
     * إضافة موظف جديد وإسناد الدور الوظيفي له
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string',
            'role' => 'required|string', // مثال: Doctor, Receptionist
        ]);

        // 1. إنشاء حساب الموظف
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);

        // 2. البحث عن الدور وإسناده في جدول الربط
        $role = Role::where('name', $request->role)->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تمت إضافة الموظف بنجاح',
            'data' => $user->load('roles'),
        ], 201);
    }

    /**
     * تعديل بيانات الموظف وإضافة دور وظيفي جديد (إن وجد)
     */
    public function update(Request $request, $id)
    {
        $user = User::staff()->findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string',
            'role' => 'nullable|string',
        ]);

        // 1. تجهيز بيانات التحديث للحقول المرسلة فقط
        $data = [];

        if ($request->filled('name')) {
            $data['name'] = $request->name;
        }
        if ($request->filled('email')) {
            $data['email'] = $request->email;
        }
        if ($request->filled('phone')) {
            $data['phone'] = $request->phone;
        }
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // تنفيذ التحديث إذا وُجدت بيانات جديدة
        if (! empty($data)) {
            $user->update($data);
        }

        // 2. إسناد دور جديد بجانب الأدوار القديمة دون حذفها
        if ($request->filled('role')) {
            $role = Role::where('name', $request->role)->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث بيانات الموظف وإضافة الدور بنجاح',
            'data' => $user->load('roles'),
        ], 200);
    }

    /**
     * حذف موظف من النظام
     */
    public function destroy($id)
    {
        $user = User::staff()->findOrFail($id);

        // يتم حذف علاقاته تلقائياً من جدول الربط بفضل onDelete('cascade')
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الموظف بنجاح',
        ], 200);
    }
}