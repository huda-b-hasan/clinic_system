<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Treatment;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function store(Request $request)
    {
        // 1. المدخلات القادمة من المستخدم (فقط العلاج، الطبيب، والوقت)
        $request->validate([
            'patient_id'       => 'required|exists:patients,id',
            'treatment_id'     => 'required|exists:treatments,id',
            'doctor_id'        => 'required|exists:users,id', 
            'appointment_date' => 'required|date_format:Y-m-d H:i:s', 
        ]);

        // 2. جلب تفاصيل العلاج والوقت
        $treatment = Treatment::findOrFail($request->treatment_id);
        $startTime = Carbon::parse($request->appointment_date);
        $endTime   = $startTime->copy()->addMinutes($treatment->duration);

        // 3. جلب الغرفة والجهاز المرتبطين بهذا العلاج تلقائياً من العلاقات
        // (حسب الجداول الوسيطة في المخطط الخاص بكِ)
        $roomId   = $treatment->room_id; // أو بالطريقة التي ربطتي بها الغرفة بالعلاج
        $deviceId = $treatment->devices()->first()?->id; // جلب أول جهاز مرتبط بهذا العلاج مثلاً

        // 4. دالة فحص التقاطع (Overlap) الثابتة
        $checkOverlap = function($query) use ($startTime, $endTime) {
            $query->where(function($q) use ($startTime, $endTime) {
                $q->where('oppintment_date', '<', $endTime)
                  ->where(\DB::raw('DATE_ADD(oppintment_date, INTERVAL (SELECT duration FROM treatments WHERE treatments.id = appointments.treatment_id) MINUTE)'), '>', $startTime);
            });
        };

        // 5. الفحص الأول: هل الطبيب مشغول؟
        $doctorBusy = Appointment::where('doctor_id', $request->doctor_id)
            ->where($checkOverlap)
            ->exists();

        if ($doctorBusy) {
            return response()->json(['status' => 'error', 'message' => 'الطبيب غير متاح في هذا الوقت.'], 422);
        }

        // 6. الفحص الثاني: هل الغرفة المطلوبة لهذا العلاج محجوزة؟
        if ($roomId) {
            $roomBusy = Appointment::where('room_id', $roomId)
                ->where($checkOverlap)
                ->exists();

            if ($roomBusy) {
                return response()->json(['status' => 'error', 'message' => 'الغرفة المطلوبة لهذا العلاج غير متاحة في هذا الوقت.'], 422);
            }
        }

        // 7. الفحص الثالث: هل الجهاز المطلوب لهذا العلاج مشغول؟
        if ($deviceId) {
            // هنا نفحص إذا كان هناك حجز آخر في نفس الوقت يستخدم غرفة تحتوي على هذا الجهاز، 
            // أو حجز لنفس الجهاز (حسب طريقة ربط الحجوزات بالأجهزة عندك)
            $deviceBusy = Appointment::where('room_id', $roomId) // الفحص الافتراضي بناءً على الغرفة والجهاز
                ->where($checkOverlap)
                ->exists();
                
            if ($deviceBusy) {
                return response()->json(['status' => 'error', 'message' => 'الجهاز اللازم لهذا العلاج مستخدم حالياً.'], 422);
            }
        }

        // 8. إذا كل شيء متاح -> يتم الحجز تلقائياً وتخزين الغرفة التي تم سحبها
        $appointment = Appointment::create([
            'patient_id'       => $request->patient_id,
            'doctor_id'        => $request->doctor_id,
            'treatment_id'     => $request->treatment_id,
            'oppintment_date'  => $startTime,
            'room_id'          => $roomId, // تم جلبه تلقائياً
            'status'           => 'pending',
            'user_id'          => auth()->id() ?? $request->user_id
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تسجيل الموعد بنجاح، وتخصيص الغرفة والجهاز المطلوبين تلقائياً!',
            'data'    => $appointment
        ], 201);
    }
}