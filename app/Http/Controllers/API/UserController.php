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
     * عرض قائمة الموظفين والإحصائيات
     */
    public function index(Request $request)
    {
        // 1. الإحصائيات (تعتمد على Scopes الموديل وتستثني المرضى تلقائياً)
        $stats = [
            'total_users' => User::staff()->count(),
            'doctors_count' => User::staff()->doctor()->count(),
            'reseptions_count' => User::staff()->receptionist()->count(),
        ];

        // 2. الاستعلام الأساسي (الموظفون فقط + جلب الأدوار)
        $query = User::staff()->with('roles');

        // البحث (الاسم، البريد، الهاتف)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // الفلترة بحسب الدور
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
                'roles' => $user->roles->pluck('name')->toArray(),            ];
        });

        // إرجاع الاستجابة بأسلوب JSON لـ AJAX
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => $stats,
                    'users' => $users,
                ],
            ]);
        }

    }

    /**
     * إضافة موظف جديد وإسناد الدور له
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string',
            'role' => 'required|string', // اسم الدور مثل: Doctor, Receptionist
        ]);

        // 1. إنشاء المستخدم
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);

        // 2. البحث عن الدور وإسناده في جدول role_user
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
     * تعديل بيانات الموظف ودوره
     */
    public function update(Request $request, $id)
    {
        $user = User::staff()->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string',
            'role' => 'required|string',
        ]);

        // 1. تحديث البيانات الأساسية
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // 2. تحديث الدور في جدول role_user (sync تحذف القديم وتضيف الجديد)
        $role = Role::where('name', $request->role)->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل بيانات الموظف بنجاح',
            'data' => $user->load('roles'),
        ]);
    }

    /**
     * حذف موظف
     */
    public function destroy($id)
    {
        $user = User::staff()->findOrFail($id);

        // سيتم حذف علاقاته تلقائياً من role_user بفضل onDelete('cascade')
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الموظف بنجاح',
        ]);
    }
}
