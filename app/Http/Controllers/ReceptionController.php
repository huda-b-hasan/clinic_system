<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceptionController extends Controller
{
    /**
     * جلب قائمة جميع موظفي الاستقبال (المعرف والاسم فقط)
     */
    public function getAllReceptionist(): JsonResponse
    {
        try {
            $receptionists = User::whereHas('roles', function ($query) {
                $query->where('name', 'Receptionist');
            })
                ->select('id', 'name')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $receptionists,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في السيرفر: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب ملف موظف الاستقبال الحالي المسجل دخوله عبر الجلسة (Session)
     */
    public function getCurrentReceptionistProfile(Request $request): JsonResponse
    {
        try {
            $receptionistId = session('user_id');

            if (! $receptionistId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لم يتم العثور على موظف استقبال مسجل حالياً في الجلسة.',
                ], 401);
            }

            $receptionist = User::find($receptionistId);

            if (! $receptionist) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'بيانات المستخدم غير موجودة في النظام.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $receptionist->id,
                    'name' => $receptionist->name,
                    'email' => $receptionist->email,
                    'phone' => $receptionist->phone,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب إحصائيات لوحة التحكم الكاملة للاستقبال (المواعيد المفصلة وحالة الغرف الحية)
     */
    public function getReceptionDashboardStats(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today();

            // ==========================================
            // 1. حساب الإحصائيات السريعة (الكروت العلوية)
            // ==========================================
            $todayAppointmentsCount = Appointment::whereDate('appointment_date', $today)->count();

            $waitingPatientsCount = Appointment::whereDate('appointment_date', $today)
                ->where('status', 'pending')
                ->count();

            $unpaidBillsCount = Bill::where('status', 'unpaid')->count();

            $totalRoomsCount = Room::count();
            
            // الغرفة مشغولة إذا كان الموعد بحالة 'active' اليوم
            $occupiedRoomsCount = Appointment::whereDate('appointment_date', $today)
                ->where('status', 'active')
                ->distinct('room_id')
                ->count('room_id');

            // ==========================================
            // 2. تصفية المواعيد وتفاصيلها حسب الحالة
            // ==========================================
            $todayAppointments = Appointment::whereDate('appointment_date', $today)
                ->with(['patient', 'doctor', 'room', 'treatments'])
                ->get();

            $pendingAppointments = [];
            $completedAppointments = [];
            $canceledAppointments = [];

            foreach ($todayAppointments as $appointment) {
                $appointmentData = [
                    'id' => $appointment->id,
                    'patient_name' => $appointment->patient->name ?? 'مريض غير معروف',
                    'doctor_name' => $appointment->doctor->name ?? 'غير محدد',
                    'treatment_name' => $appointment->treatments->first()->name ?? 'إجراء عام',
                    'room_name' => $appointment->room->name ?? 'بدون غرفة',
                    'time' => Carbon::parse($appointment->appointment_date)->format('H:i'),
                ];

                if ($appointment->status === 'pending') {
                    $pendingAppointments[] = $appointmentData;
                } elseif ($appointment->status === 'completed') {
                    $completedAppointments[] = $appointmentData;
                } elseif ($appointment->status === 'canceled') {
                    $canceledAppointments[] = $appointmentData;
                }
            }

            // ==========================================
            // 3. جلب حالة الغرف وتفاصيلها الحية
            // ==========================================
            $rooms = Room::all();
            $roomsDetails = [];

            foreach ($rooms as $room) {
                $currentActiveAppointment = Appointment::where('room_id', $room->id)
                    ->whereDate('appointment_date', $today)
                    ->where('status', 'active')
                    ->with(['patient', 'doctor', 'treatments'])
                    ->first();

                if ($currentActiveAppointment) {
                    $roomsDetails[] = [
                        'room_name' => $room->name,
                        'status' => 'occupied',
                        'patient_name' => $currentActiveAppointment->patient->name ?? 'مريض غير معروف',
                        'doctor_name' => $currentActiveAppointment->doctor->name ?? 'غير محدد',
                        'treatment_name' => $currentActiveAppointment->treatments->first()->name ?? 'إجراء عام',
                    ];
                } else {
                    $roomsDetails[] = [
                        'room_name' => $room->name,
                        'status' => 'available',
                        'patient_name' => null,
                        'doctor_name' => null,
                        'treatment_name' => null,
                    ];
                }
            }

            // ==========================================
            // 4. إرجاع النتيجة المتكاملة
            // ==========================================
            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => [
                        'today_appointments' => $todayAppointmentsCount,
                        'occupied_rooms' => "{$occupiedRoomsCount} / {$totalRoomsCount}",
                        'waiting_patients' => $waitingPatientsCount,
                        'unpaid_bills' => $unpaidBillsCount,
                    ],
                    'appointments' => [
                        'pending' => $pendingAppointments,
                        'completed' => $completedAppointments,
                        'canceled' => $canceledAppointments,
                    ],
                    'rooms' => $roomsDetails,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'فشل جلب الإحصائيات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تسجيل حضور المريض وإكمال الموعد (تحديث الحالة إلى مكتمل)
     */
    public function attendAppointment(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل حضور المريض وإكمال الموعد.',
        ], 200);
    }

    /**
     * إلغاء موعد مريض
     */
    public function cancelAppointment(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->update(['status' => 'canceled']);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الموعد بنجاح.',
        ], 200);
    }
}