<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicSessions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function getAllDoctors()
    {
        try {
            $doctors = User::whereHas('roles', function ($query) {
                $query->where('name', 'Doctor');
            })
                ->select('id', 'name')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $doctors,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في السيرفر: '.$e->getMessage(),
            ], 500);
        }
    }

    // dashboard doctor

    public function getCurrentDoctorProfile(Request $request)
    {
        try {
            // 1. جلب الـ ID الخاص بالطبيب من السشن
            $doctorId = session('user_id');

            if (! $doctorId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لم يتم العثور على طبيب مسجل حالياً في الجلسة.',
                ], 401); //  تعني غير مصرح له
            }

            // 2. جلب بيانات الطبيب بالكامل
            $doctor = User::doctor() // استخدام الـ Scope للتأكد من أنه طبيب فعلاً
                ->where('id', $doctorId)
                ->first();

            if (! $doctor) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'بيانات الطبيب غير موجودة في النظام.',
                ], 404);
            }

            // 3. إرجاع البيانات بنجاح
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'email' => $doctor->email,
                    'phone' => $doctor->phone, // الحقل الذي أضفناه سوياً في السيرفر
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDashboardDoctor(Request $request)
    {
        try {
            $doctorId = session('user_id');
            $today = Carbon::today()->toDateString();

            // 1. حساب كروت الإحصائيات
            $todayAppointmentsCount = Appointment::where('doctor_id', $doctorId)
                ->where('status', 'pending')
                ->whereDate('appointment_date', $today)
                ->count();

            $totalPatientsCount = Appointment::where('doctor_id', $doctorId)
                ->where('status', 'completed')
                ->distinct('patient_id')
                ->count('patient_id');

            $totalCompletedSessionsCount = ClinicSessions::whereHas('appointment', function ($query) use ($doctorId) {

                $query->where('doctor_id', $doctorId);

            })->count();

            // 2. جلب مواعيد اليوم
            $todayAppointments = Appointment::where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $today)
                ->with(['patient', 'room', 'treatments'])
                ->get();

            $pendingAppointments = $todayAppointments->where('status', 'pending')->values()->toArray();
            $cancelledAppointments = $todayAppointments->where('status', 'canceled')->values()->toArray();
            $completedAppointments = $todayAppointments->where('status', 'completed')->values()->toArray();

            // 3. جلب كل الجلسات التي نفذها هذا الطبيب
            $doctorSessions = ClinicSessions::whereHas('appointment', function ($query) use ($doctorId) {
                $query->where('doctor_id', $doctorId);
            })
                ->with(['appointment.patient', 'appointment.treatments', 'bill'])
                ->latest() // ترتيب الجلسات من الأحدث إلى الأقدم
                ->get()
                ->toArray();

            $totalPatients = Appointment::where('doctor_id', $doctorId)
                ->where('status', 'completed')
                ->with('patient')
                ->get()
                ->pluck('patient')
                ->unique('id')
                ->values()
                ->toArray();

            // 4. إرجاع البيانات كاملة كـ JSON
            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => [
                        'today_appointments_count' => $todayAppointmentsCount,
                        'total_patients_count' => $totalPatientsCount,
                        'total_completed_sessions_count' => $totalCompletedSessionsCount,
                    ],
                    'appointments' => [
                        'pending' => $pendingAppointments,
                        'cancelled' => $cancelledAppointments,
                        'completed' => $completedAppointments,
                    ],
                    'sessions' => $doctorSessions,
                    'pateints' => $totalPatients,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function getDoctorAppointments(Request $request)
    {
        try {
            $doctorId = session('user_id');

            if (! $doctorId) {
                return response()->json(['success' => false, 'message' => 'لم يتم التعرف على الطبيب، يرجى إعادة تسجيل الدخول'], 401);
            }

            $appointments = Appointment::where('doctor_id', $doctorId)
                ->with(['patient', 'room', 'treatments'])
                ->orderBy('appointment_date', 'asc')
                ->get();

            $completed = $appointments->where('status', 'completed')->values();
            $pending = $appointments->where('status', 'pending')->values();
            $cancelled = $appointments->where('status', 'cancelled')->values();

            return response()->json([
                'success' => true,
                'doctor_id' => $doctorId,
                'completed' => $completed,
                'pending' => $pending,
                'cancelled' => $cancelled,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
