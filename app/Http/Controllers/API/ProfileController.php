<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * جلب البيانات وعرضها
     */
    public function show(Request $request)
    {
        $userId = session('user_id');

        // جلب بيانات المستخدم الأساسية
        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        // قراءة الدور من السيشن مباشرة كما يخزنه الـ AuthController (مثل Patient)
        $userRole = session('user_role');

        // جلب بيانات ملف المريض إذا كان الدور Patient
        $patientData = null;
        if (strtolower($userRole) === 'patient') {
            $patientData = DB::table('patients')->where('user_id', $userId)->first();
        }

        return response()->json([
            'user_id'   => $user->id,
            'email'     => $user->email,
            'role'      => $userRole,
            'profile'   => [
                'name'          => $user->name,
                'phone'         => $user->phone,
                'gender'        => $patientData ? $patientData->gender : null,
                'birthdate'     => $patientData ? $patientData->birthdate : null,
                'address'       => $patientData ? $patientData->address : null,
                'medical_notes' => $patientData ? $patientData->medical_notes : null,
            ]
        ], 200);
    }

    /**
     * تحديث البيانات المضمون
     */
    public function update(Request $request)
    {
        $userId = session('user_id');

        if (!$userId) {
            return response()->json(['message' => 'غير مصرح بالدخول.'], 401);
        }

        // 1. تحديث جدول الـ users الأساسي فوراً
        DB::table('users')->where('id', $userId)->update([
            'name'       => $request->name,
            'phone'      => $request->phone,
            'updated_at' => now(),
        ]);

        // 2. قراءة الدور من السيسشن مع تحويله لحروف صغيرة لضمان نجاح الشرط دائماً
        $userRole = strtolower(session('user_role'));

        if ($userRole === 'patient') {
            // 3. تحديث جدول الـ patients باستخدام الـ user_id
            DB::table('patients')->where('user_id', $userId)->update([
                'name'          => $request->name,
                'phone'         => $request->phone,
                'birthdate'     => $request->birthdate,
                'address'       => $request->address,
                'medical_notes' => $request->medical_notes,
                'updated_at'    => now(),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث ملفكِ الشخصي بنجاح!'
        ], 200);
    }
}