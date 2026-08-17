<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PromoCode;
use App\Models\Room;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * تسجيل موعد جديد (مع فحص الفواتير غير المدفوعة، أكواد الخصم، ساعات العمل، والتعارضات)
     */
    public function storeAppointment(Request $request)
    {
        // 1. تحديد user_id تلقائياً من الجلسة أو الـ Auth أو الطلب
        $userId = auth()->id() ?? session('user_id') ?? $request->user_id;

        if (! $userId) {
            return response()->json([
                'status' => false,
                'message' => 'عذراً، لم يتم التعرف على جلسة المستخدم. يرجى إعادة تسجيل الدخول.',
            ], 422);
        }

        // 2. التحقق من صحة المدخلات
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:users,id',
            'room_id' => 'nullable|exists:rooms,id',
            'appointment_date' => 'required|date_format:Y-m-d H:i:s',
            'treatment_ids' => 'required|array',
            'treatment_ids.*' => 'exists:treatments,id',
            'promo_code' => 'nullable|string',
        ]);

        // تحديد الغرفة تلقائياً إذا لم يتم إرسالها في الطلب
        $roomId = $request->room_id;

        if (! $roomId) {
            // جلب أول غرفة تكون حالتها متاحة  في النظام
            $firstRoom = Room::where('status', 'available')->first();

            if (! $firstRoom) {
                return response()->json([
                    'status' => false,
                    'message' => 'عذراً، لا توجد أي غرف متاحة حالياً في العيادة.',
                ], 422);
            }

            $roomId = $firstRoom->id;
        }
        // 3. التحقق من عدم وجود فواتير سابقة غير مدفوعة للمريض
        $hasUnpaidBills = Appointment::where('patient_id', $request->patient_id)
            ->whereHas('clinicSession.bill', function ($query) {
                $query->where('status', 'unpaid');
            })->exists();

        if ($hasUnpaidBills) {
            return response()->json([
                'status' => false,
                'message' => 'عذراً، لا يمكن إتمام الحجز بسبب وجود فواتير سابقة غير مدفوعة',
            ], 422);
        }

        // 4. فحص صحة وفعالية كود الخصم (إن وجد)
        $promoCode = null;
        if ($request->filled('promo_code')) {
            $promoCode = PromoCode::where('code', $request->promo_code)->first();

            if (! $promoCode) {
                return response()->json(['status' => false, 'message' => 'كود الخصم المدخل غير صحيح أو غير موجود.'], 422);
            }

            if (! $promoCode->is_active) {
                return response()->json(['status' => false, 'message' => 'عذراً، هذا الكود غير نشط حالياً ولا يمكن استخدامه.'], 422);
            }

            if (Carbon::parse($promoCode->expiry_date)->isPast()) {
                return response()->json(['status' => false, 'message' => 'عذراً، انتهت صلاحية استخدام هذا الكود.'], 422);
            }

            if ($promoCode->usage_limit !== null && $promoCode->used_count >= $promoCode->usage_limit) {
                return response()->json(['status' => false, 'message' => 'عذراً، نفدت الكمية المتاحة لاستخدام هذا الكود.'], 422);
            }

            // التحقق مما إذا كان المريض قد استخدم الكود مسبقاً
            $alreadyUsed = DB::table('promo_code_patient')
                ->where('patient_id', $request->patient_id)
                ->where('promo_code_id', $promoCode->id)
                ->exists();

            if ($alreadyUsed) {
                return response()->json(['status' => false, 'message' => 'لقد قمت باستخدام هذا الكود من قبل.'], 422);
            }

            if ($promoCode->treatment_id !== null && ! in_array($promoCode->treatment_id, $request->treatment_ids)) {
                return response()->json(['status' => false, 'message' => 'هذا الكود مخصص لخدمة علاجية معينة وغير صالح للخدمات المحددة في هذا الحجز.'], 422);
            }
        }

        // 5. حساب وقت البدء والانتهاء للموعد وفحص ساعات العمل الرسمية للعيادة (10:00 صباحاً - 10:00 مساءً)
        $startTime = Carbon::parse($request->appointment_date);
        $totalDuration = (int) Treatment::whereIn('id', $request->treatment_ids)->sum('duration');
        $endTime = $startTime->copy()->addMinutes($totalDuration);

        $clinicOpenTime = $startTime->copy()->setTime(10, 0, 0);  // 10:00 صباحاً
        $clinicCloseTime = $startTime->copy()->setTime(22, 0, 0); // 10:00 مساءً

        if ($startTime->lessThan($clinicOpenTime) || $endTime->greaterThan($clinicCloseTime)) {
            return response()->json([
                'status' => false,
                'message' => 'عذراً، العيادة مغلقة في هذا الوقت. مواعيد العمل الرسمية من الساعة 10:00 صباحاً وحتى 10:00 مساءً.',
            ], 422);
        }

        // 6. جلب المواعيد الموجودة في نفس اليوم لفحص التعارضات
        $existingAppointments = Appointment::with('treatments')
            ->whereDate('appointment_date', $startTime->toDateString())
            ->get();

        $hasConflict = function ($existingApp) use ($startTime, $endTime) {
            $appStart = Carbon::parse($existingApp->appointment_date);
            $appDuration = $existingApp->treatments->sum('duration');
            $appEnd = $appStart->copy()->addMinutes($appDuration);

            return $startTime->lessThan($appEnd) && $endTime->greaterThan($appStart);
        };

        // فحص تعارض جدول الطبيب
        $doctorBusy = $existingAppointments
            ->where('doctor_id', $request->doctor_id)
            ->contains($hasConflict);

        if ($doctorBusy) {
            return response()->json(['status' => false, 'message' => 'الطبيب مشغول في هذا الوقت، يرجى اختيار وقت آخر.'], 422);
        }

        // فحص تعارض الغرفة
        $roomBusy = $existingAppointments
            ->where('room_id', $roomId)
            ->contains($hasConflict);

        if ($roomBusy) {
            return response()->json(['status' => false, 'message' => 'الغرفة المختارة غير متاحة في هذا الوقت.'], 422);
        }

        // 7. فحص تعارض الأجهزة الطبية المرتبطة بالخدمات المطلوبة
        $requiredDeviceIds = Treatment::whereIn('id', $request->treatment_ids)
            ->with('devices')
            ->get()
            ->pluck('devices.*.id')
            ->flatten()
            ->unique()
            ->toArray();

        if (! empty($requiredDeviceIds)) {
            $deviceConflict = $existingAppointments->filter($hasConflict)->contains(function ($appointment) use ($requiredDeviceIds) {
                $currentAppDeviceIds = $appointment->treatments
                    ->loadMissing('devices')
                    ->pluck('devices.*.id')
                    ->flatten()
                    ->unique()
                    ->toArray();

                return ! empty(array_intersect($requiredDeviceIds, $currentAppDeviceIds));
            });

            if ($deviceConflict) {
                return response()->json(['status' => false, 'message' => 'الأجهزة الطبية اللازمة لهذه المعالجة مستخدمة حالياً في موعد آخر.'], 422);
            }
        }

        // 8. حفظ الموعد في قاعدة البيانات وتطبيق الخصومات ضمن معاملة 
        DB::beginTransaction();
        try {
            $appointment = Appointment::create([
                'patient_id' => $request->patient_id,
                'doctor_id' => $request->doctor_id,
                'user_id' => $userId,
                'room_id' => $roomId,
                'appointment_date' => $startTime->toDateTimeString(),
                'status' => 'pending',
            ]);

            $treatments = Treatment::whereIn('id', $request->treatment_ids)->get();
            $syncData = [];

            foreach ($treatments as $treatment) {
                $finalPrice = $treatment->discount_price ?? $treatment->base_price;
                $appliedPromoId = null;

                if ($promoCode) {
                    if ($promoCode->treatment_id == $treatment->id || $promoCode->treatment_id === null) {
                        $appliedPromoId = $promoCode->id;

                        if ($promoCode->discount_type === 'percentage') {
                            $finalPrice = $finalPrice - ($finalPrice * ($promoCode->discount_value / 100));
                        } elseif ($promoCode->discount_type === 'fixed') {
                            $finalPrice = $finalPrice - $promoCode->discount_value;
                        }

                        if ($finalPrice < 0) {
                            $finalPrice = 0.00;
                        }
                    }
                }

                $syncData[$treatment->id] = [
                    'booked_price' => round($finalPrice, 2),
                    'promo_code_id' => $appliedPromoId,
                ];
            }

            // ربط الخدمات بالموعد في الجدول الوسيط
            $appointment->treatments()->attach($syncData);

            // تحديث عدد مرات استخدام كود الخصم وسجله للمريض
            if ($promoCode) {
                $promoCode->increment('used_count');

                DB::table('promo_code_patient')->insert([
                    'patient_id' => $request->patient_id,
                    'promo_code_id' => $promoCode->id,
                    'used_at' => Carbon::now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تسجيل الموعد بنجاح.',
                'data' => $appointment->load('treatments'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['status' => false, 'message' => 'حدث خطأ غير متوقع: '.$e->getMessage()], 500);
        }
    }

    /**
     * إلغاء موعد مع مراعاة الصلاحيات وشرط الـ 24 ساعة للمريض
     */
    public function cancelAppointment(Request $request, $id)
    {
        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $appointment = Appointment::findOrFail($id);

        if ($appointment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الموعد لأنه تم معالجته مسبقاً أو ملغي بالفعل.',
            ], 400);
        }

        $currentRole = session('user_role');
        $cancelledVia = 'system';

        if ($currentRole === 'Patient') {
            $cancelledVia = 'Patient';

            // منع المريض من الإلغاء إذا بقي أقل من 24 ساعة على الموعد
            if (now()->diffInHours($appointment->appointment_date, false) < 24) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، لا يمكن الإلغاء قبل أقل من 24 ساعة من الموعد.',
                ], 422);
            }
        } elseif ($currentRole === 'Doctor') {
            $cancelledVia = 'Doctor';
        } elseif (in_array($currentRole, ['Receptionist', 'Manager'])) {
            $cancelledVia = 'Receptionist';
        } 

        // تحديث حالة الموعد إلى ملغي وتسجيل سبب الإلغاء
        $appointment->update([
            'status' => 'canceled',
            'cancelled_via' => $cancelledVia,
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الموعد بنجاح',
            'data' => $appointment,
        ], 200);
    }

    /**
     * تحديث حالة قراءة إشعار الإلغاء من قبل المريض
     */
    public function markAsSeen(Request $request, $id)
    {
        $appointment = Appointment::where('id', $id)->first();

        if ($appointment) {
            $appointment->update([
                'patient_saw_cancellation' => true,
                'cancellation_seen_at' => now(),
            ]);

            return response()->json(['message' => 'تم التحديث بنجاح']);
        }

        return response()->json(['message' => 'الموعد غير موجود'], 404);
    }

    /**
     * جلب المواعيد مقسمة حسب حالتها (مع الإحصائيات والأعداد)
     */
    public function getCategorizedAppointments(Request $request)
    {
        $appointments = Appointment::with(['patient', 'doctor', 'room', 'treatments'])
            ->orderBy('appointment_date', 'desc')
            ->get();

        $pendingAppointments = $appointments->where('status', 'pending')->values();
        $completedAppointments = $appointments->where('status', 'completed')->values();
        $cancelledAppointments = $appointments->where('status', 'canceled')->values();
        $inProgressAppointments = $appointments->where('status', 'in_progress')->values();
        $arrivedAppointments = $appointments->where('status', 'arrived')->values();

        return response()->json([
            'status' => 'success',
            'counts' => [
                'total' => $appointments->count(),
                'pending' => $pendingAppointments->count(),
                'completed' => $completedAppointments->count(),
                'cancelled' => $cancelledAppointments->count(),
                'in_progress' => $inProgressAppointments->count(),
                'arrived' => $arrivedAppointments->count(),
            ],
            'data' => [
                'pending' => $pendingAppointments,
                'completed' => $completedAppointments,
                'cancelled' => $cancelledAppointments,
                'in_progress' => $inProgressAppointments,
                'arrived' => $arrivedAppointments,
            ],
        ], 200);
    }

    /**
     * تسجيل حضور المريض (تغيير الحالة إلى arrived)
     */
    public function markAsArrived($id)
    {
        $appointment = Appointment::findOrFail($id);

        $appointment->update([
            'status' => 'arrived',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل حضور المريض بنجاح',
            'data' => $appointment,
        ], 200);
    }

    /**
     * إدخال المريض للغرفة لبدء الجلسة وتحديث حالة الغرفة إلى مشغولة
     */
    public function startSession($id)
    {
        $appointment = Appointment::findOrFail($id);

        $appointment->update([
            'status' => 'in_progress',
        ]);

        // تحديث حالة الغرفة لتصبح مشغولة تلقائياً
        if ($appointment->room_id) {
            Room::where('id', $appointment->room_id)->update(['status' => 'busy']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم إدخال المريض للغرفة وبدء الجلسة',
            'data' => $appointment,
        ], 200);
    }

    /**
     * تحديث تفاصيل وموعد الحجز القائم
     */
    public function updateAppointment(Request $request, $id)
    {
        $appointment = Appointment::find($id);

        if (! $appointment) {
            return response()->json([
                'status' => false,
                'message' => 'الموعد غير موجود',
            ], 404);
        }

        $request->validate([
            'doctor_id' => 'required',
            'appointment_date' => 'required',
        ]);

        // 1. تحديث البيانات الأساسية للموعد
        $appointment->update([
            'doctor_id' => $request->doctor_id,
            'patient_id' => $request->patient_id ?? $appointment->patient_id,
            'appointment_date' => $request->appointment_date,
            'notes' => $request->notes,
        ]);

        // 2. تحديث الخدمات وإعادة حساب الأسعار في الجدول الوسيط
        if ($request->has('treatment_ids') && is_array($request->treatment_ids)) {
            $treatments = Treatment::whereIn('id', $request->treatment_ids)->get();
            $syncData = [];

            foreach ($treatments as $treatment) {
                $price = $treatment->discount_price ?? $treatment->base_price ?? $treatment->price ?? 0;

                $syncData[$treatment->id] = [
                    'booked_price' => round($price, 2),
                ];
            }

            $appointment->treatments()->sync($syncData);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل الموعد بنجاح',
            'data' => $appointment->load('treatments'),
        ]);
    }

    /**
     * التحقق من صلاحية كود الخصم وإرجاع تفاصيله للواجهة الأمامية
     */
    public function validatePromoCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'patient_id' => 'nullable',
        ]);

        $promo = PromoCode::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (! $promo) {
            return response()->json([
                'status' => 'error',
                'message' => 'كود الخصم غير صحيح أو غير موجود',
            ], 404);
        }

        if ($promo->expiry_date && now()->greaterThan($promo->expiry_date)) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، انتهت صلاحية كود الخصم هذا',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تطبيق كود الخصم بنجاح!',
            'discount_type' => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
        ]);
    }
}
