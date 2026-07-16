<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReceptionController extends Controller
{
    public function getAllReceptionist()
    {
        try {
            $reseption = User::whereHas('roles', function ($query) {
                $query->where('name', 'Receptionist');
            })
                ->select('id', 'name')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $reseption,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في السيرفر: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getCurrentReceptionistProfile(Request $request)
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
    // /////////////////////////////////////////////////////////
/**
     * جلب إحصائيات لوحة التحكم بالكامل للمستقبل (المواعيد المفصلة وحالة الغرف الحية)
     */
    public function getReceptionDashboardStats(Request $request)
    {
        try {
            $today = \Carbon\Carbon::today();

            // ==========================================
            // 1. حساب الإحصائيات السريعة (الكروت العلوية)
            // ==========================================
            
            // إجمالي مواعيد اليوم
            $todayAppointmentsCount = Appointment::whereDate('appointment_date', $today)->count();

            // المواعيد التي في صالة الانتظار اليوم (حالتها pending)
            $waitingPatientsCount = Appointment::whereDate('appointment_date', $today)
                ->where('status', 'pending')
                ->count();

            // الفواتير غير المسددة
            $unpaidBillsCount = Bill::where('status', 'unpaid')->count();

            // عدد الغرف الإجمالي وعدد الغرف المشغولة حالياً
            $totalRoomsCount = Room::count();
            
            // الغرفة تعتبر "مشغولة" إذا كان هناك موعد قائم "active" أو مريض داخلها حالياً اليوم
            $occupiedRoomsCount = Appointment::whereDate('appointment_date', $today)
                ->where('status', 'active') 
                ->distinct('room_id')
                ->count('room_id');


            // ==========================================
            // 2. تصفية المواعيد وتفاصيلها في مصفوفات حسب الحالة
            // ==========================================
            
            // جلب المواعيد مع العلاقات كاملة (المريض، الطبيب، الغرفة، والخدمات)
            $todayAppointments = Appointment::whereDate('appointment_date', $today)
                ->with(['patient', 'doctor', 'room', 'treatments'])
                ->get();

            // فصل المواعيد إلى مصفوفات بناءً على الحالة
            $pendingAppointments = [];
            $completedAppointments = [];
            $canceledAppointments = [];

            foreach ($todayAppointments as $appointment) {
                // تنسيق تفاصيل الموعد بالشكل المطلوب للفرونت
                $appointmentData = [
                    'id' => $appointment->id,
                    'patient_name' => $appointment->patient->name ?? 'مريض غير معروف',
                    'doctor_name' => $appointment->doctor->name ?? 'غير محدد',
                    'treatment_name' => $appointment->treatments->first()->name ?? 'إجراء عام',
                    'room_name' => $appointment->room->name ?? 'بدون غرفة',
                    'time' => \Carbon\Carbon::parse($appointment->appointment_date)->format('H:i'),
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
                // البحث عن موعد نشط حالياً (active) داخل هذه الغرفة لليوم
                $currentActiveAppointment = Appointment::where('room_id', $room->id)
                    ->whereDate('appointment_date', $today)
                    ->where('status', 'active')
                    ->with(['patient', 'doctor', 'treatments'])
                    ->first();

                if ($currentActiveAppointment) {
                    $roomsDetails[] = [
                        'room_name' => $room->name,
                        'status' => 'occupied', // مشغولة
                        'patient_name' => $currentActiveAppointment->patient->name ?? 'مريض غير معروف',
                        'doctor_name' => $currentActiveAppointment->doctor->name ?? 'غير محدد',
                        'treatment_name' => $currentActiveAppointment->treatments->first()->name ?? 'إجراء عام',
                    ];
                } else {
                    $roomsDetails[] = [
                        'room_name' => $room->name,
                        'status' => 'available', // متاحة
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
                    'rooms' => $roomsDetails
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'فشل جلب الإحصائيات: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * جلب الإحصائيات والبيانات الحية للوحة تحكم الاستقبال (التي صممناها في HTML)
     */
    public function getDashboardData()
    {
        $today = Carbon::today();

        // 1. الإحصائيات السريعة للكروت
        $todayAppointmentsCount = Appointment::whereDate('appointment_date', $today)->count();
        $waitingPatientsCount = Appointment::whereDate('appointment_date', $today)->where('status', 'pending')->count();
        $unpaidBillsCount = Bill::where('status', 'unpaid')->count();

        // حساب الغرف المشغولة
        $totalRooms = Room::count();
        $occupiedRoomsCount = Appointment::whereDate('appointment_date', $today)
            ->where('status', 'completed') // افترضنا هنا أن 'completed' تعني أن الجلسة تمت في الغرفة
            ->distinct('room_id')
            ->count('room_id');

        // 2. جلب المواعيد مصنفة حسب التبويبات (Tabs)
        $appointments = Appointment::whereDate('appointment_date', $today)
            ->with(['patient', 'room', 'treatments']) // جلب العلاقات المرتبطة
            ->get()
            ->groupBy('status');

        // 3. حالة الغرف الحية
        $rooms = Room::all()->map(function ($room) use ($today) {
            $activeAppointment = Appointment::where('room_id', $room->id)
                ->whereDate('appointment_date', $today)
                ->where('status', 'pending')
                ->with(['patient', 'treatments'])
                ->first();

            return [
                'id' => $room->id,
                'name' => $room->name,
                'status' => $activeAppointment ? 'occupied' : 'available',
                'patient_name' => $activeAppointment ? $activeAppointment->patient->name : null,
                'treatment_name' => $activeAppointment ? ($activeAppointment->treatments->first()->name ?? 'إجراء عام') : null,
            ];
        });

        // إرجاع البيانات بصيغة JSON للفرونت إند
        return response()->json([
            'stats' => [
                'today_appointments' => $todayAppointmentsCount,
                'occupied_rooms' => "{$occupiedRoomsCount} / {$totalRooms}",
                'waiting_patients' => $waitingPatientsCount,
                'unpaid_bills' => $unpaidBillsCount,
            ],
            'appointments' => [
                'pending' => $appointments->get('pending', []),
                'completed' => $appointments->get('completed', []),
                'canceled' => $appointments->get('canceled', []),
            ],
            'rooms' => $rooms,
        ]);
    }

    /**
     * تسجيل حضور مريض وإكمال الموعد (تحديث حالة الموعد)
     */
    public function attendAppointment(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        // تحديث الحالة إلى مكتمل
        $appointment->update(['status' => 'completed']);

        // هنا يمكن إضافة أي لوجيك آخر، مثل إنشاء فاتورة تلقائياً

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل حضور المريض وإكمال الموعد.',
        ]);
    }

    /**
     * إلغاء موعد مريض
     */
    public function cancelAppointment(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        // تحديث الحالة إلى ملغى
        $appointment->update(['status' => 'canceled']);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الموعد بنجاح.',
        ]);
    }
}
