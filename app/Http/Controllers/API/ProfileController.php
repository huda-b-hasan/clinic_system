<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * جلب بيانات الملف الشخصي للمستخدم الحالي مع تفاصيل المريض إن وجد
     */
    public function show(Request $request)
    {
        $userId = session('user_id');

        // جلب بيانات المستخدم الأساسية
        $user = DB::table('users')->where('id', $userId)->first();

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        // قراءة الدور من السيشن مباشرة
        $userRole = session('user_role');

        // جلب بيانات ملف المريض إذا كان الدور Patient
        $patientData = null;
        if (strtolower($userRole) === 'patient') {
            $patientData = DB::table('patients')->where('user_id', $userId)->first();
        }

        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $userRole,
            'profile' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'gender' => $patientData ? $patientData->gender : null,
                'birthdate' => $patientData ? $patientData->birthdate : null,
                'address' => $patientData ? $patientData->address : null,
                'medical_notes' => $patientData ? $patientData->medical_notes : null,
            ],
        ], 200);
    }

    /**
     * تحديث بيانات الملف الشخصي وكلمة المرور ومعلومات المريض
     */
    public function update(Request $request)
    {
        $userId = session('user_id');

        if (! $userId) {
            return response()->json(['message' => 'غير مصرح بالدخول.'], 401);
        }

        // 1. التحقق من صحة المدخلات (Validation)
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'current_password' => 'nullable|required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ], [
            'current_password.required_with' => 'يرجى إدخال كلمة المرور الحالية لتتمكني من تغييرها.',
            'new_password.min' => 'يجب أن تكون كلمة المرور الجديدة 8 أحرف على الأقل.',
            'new_password.confirmed' => 'تأكيد كلمة المرور الجديدة غير مطابق.',
        ]);

        // 2. البحث عن المستخدم باستخدام 
        $user = User::find($userId);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        // 3. التحقق من كلمة المرور القديمة وتحديثها إن طلبت المستخدمة ذلك
        if ($request->filled('new_password')) {
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'كلمة المرور الحالية غير صحيحة.',
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
        }

        // 4. تحديث بيانات المستخدم الأساسية وحفظها
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->save();

        // 5. تحديث جدول المرضى  إذا كان دور المستخدم مريضاً
        $userRole = strtolower(session('user_role'));

        if ($userRole === 'patient') {
            $patient = Patient::where('user_id', $userId)->first();

            if ($patient) {
                $patient->update([
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'birthdate' => $request->birthdate,
                    'address' => $request->address,
                    'medical_notes' => $request->medical_notes,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث ملفكِ الشخصي بنجاح!',
        ], 200);
    }
}