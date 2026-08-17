<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClinicSessions;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

class PatientSessionController extends Controller
{
    /**
     * جلب جميع الجلسات العلاجية الخاصة بالمريض الحالي مع تفاصيل الموعد، الطبيب، المعالجات، والفاتورة
     */
    public function mySessions(Request $request)
    {
        try {
            // 1. التحقق من المصادقة )
            $user = $request->user();

            if (! $user) {
                $userId = session('user_id');
                if ($userId) {
                    $user = User::find($userId);
                }
            }

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل دخوله أو انتهت صلاحية الجلسة.',
                ], 401);
            }

            // 2. البحث عن ملف المريض المرتبط بالحساب
            $patient = $user->patient ?? Patient::where('user_id', $user->id)->first();

            if (! $patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
                ], 404);
            }

            // 3. جلب الجلسات العلاجية المرتبطة بمواعيد المريض مع العلاقات المطلوبة
            $sessions = ClinicSessions::whereHas('appointment', function ($query) use ($patient) {
                $query->where('patient_id', $patient->id);
            })
            ->with([
                'appointment' => function ($query) {
                    $query->select('id', 'appointment_date', 'status', 'doctor_id')
                          ->with([
                              'doctor' => fn($q) => $q->select('id', 'name'),
                              'treatments' => fn($q) => $q->select('treatments.id', 'name'),
                          ]);
                },
                'bill',
            ])
            ->latest()
            ->get();

            return response()->json([
                'status' => 'success',
                'count' => $sessions->count(),
                'data' => $sessions,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'error_type' => 'Server Exception Caught',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}