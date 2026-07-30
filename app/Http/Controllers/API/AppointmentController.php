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
    /*
    public function storeAppointment(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'user_id' => 'required_without:auth_id',
            'doctor_id' => 'required|exists:users,id',
            'room_id' => 'required|exists:rooms,id',
            'appointment_date' => 'required|date_format:Y-m-d H:i:s',
            'treatment_ids' => 'required|array',
            'treatment_ids.*' => 'exists:treatments,id',
            'promo_code' => 'nullable|string',
        ]);

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

            $alreadyUsed = DB::table('promo_code_patient') // هذا جدول وسيط بسيط (لا بأس بـ DB هنا أو يمكنك إنشاء موديل له)
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

        // 4. حساب وقت البدء ووقت الانتهاء المتوقع وفحص مواعيد عمل العيادة
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

        $existingAppointments = Appointment::with('treatments')
            ->whereDate('appointment_date', $startTime->toDateString())
            ->get();

        $hasConflict = function ($existingApp) use ($startTime, $endTime) {
            $appStart = Carbon::parse($existingApp->appointment_date);
            $appDuration = $existingApp->treatments->sum('duration');
            $appEnd = $appStart->copy()->addMinutes($appDuration);

            return $startTime->lessThan($appEnd) && $endTime->greaterThan($appStart);
        };

        $doctorBusy = $existingAppointments
            ->where('doctor_id', $request->doctor_id)
            ->contains($hasConflict);

        if ($doctorBusy) {
            return response()->json(['status' => false, 'message' => 'الطبيب مشغول في هذا الوقت، يرجى اختيار وقت آخر.'], 422);
        }

        $roomBusy = $existingAppointments
            ->where('room_id', $request->room_id)
            ->contains($hasConflict);

        if ($roomBusy) {
            return response()->json(['status' => false,
                'message' => 'الغرفة المختارة غير متاحة في هذا الوقت.'], 422);
        }

        // 7. فحص تعارض الأجهزة الطبية المرتبطة بالطلب
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


        DB::beginTransaction();
        try {
            $appointment = Appointment::create([
                'patient_id' => $request->patient_id,
                'doctor_id' => $request->doctor_id,
                'user_id' => auth()->id() ?: $request->user_id,
                'room_id' => $request->room_id,
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

            $appointment->treatments()->attach($syncData);

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

    // 2. التعديل على Validation: جعل room_id و user_id اختياريين لتفادي خطأ 422
    $request->validate([
        'patient_id' => 'required|exists:patients,id',
        'doctor_id' => 'required|exists:users,id',
        'room_id' => 'nullable|exists:rooms,id',
        'appointment_date' => 'required|date_format:Y-m-d H:i:s',
        'treatment_ids' => 'required|array',
        'treatment_ids.*' => 'exists:treatments,id',
        'promo_code' => 'nullable|string',
    ]);

    // تحديد الغرفة (إذا لم تُرسل من الواجهة نضع الغرفة 1 كـ Default)
    $roomId = $request->room_id ?? 1;

    // 3. فحص وجود فواتير سابقة غير مدفوعة للمريض
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

    // 4. فحص كود الخصم
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

    // 5. حساب أوقات الموعد وفحص ساعات العمل
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

    // 6. جلب المواعيد لفحص التعارض
    $existingAppointments = Appointment::with('treatments')
        ->whereDate('appointment_date', $startTime->toDateString())
        ->get();

    $hasConflict = function ($existingApp) use ($startTime, $endTime) {
        $appStart = Carbon::parse($existingApp->appointment_date);
        $appDuration = $existingApp->treatments->sum('duration');
        $appEnd = $appStart->copy()->addMinutes($appDuration);

        return $startTime->lessThan($appEnd) && $endTime->greaterThan($appStart);
    };

    // فحص تعارض الطبيب
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

    // 7. فحص الأجهزة الطبية
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

    // 8. حفظ الموعد وتطبيق الخصم
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

        $appointment->treatments()->attach($syncData);

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
        $cancelledVia = 'system'; // القيمة الافتراضية

        if ($currentRole === 'Patient') {
            $cancelledVia = 'Patient';

            if (now()->diffInHours($appointment->appointment_date, false) < 24) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، لا يمكن الإلغاء قبل أقل من 24 ساعة من الموعد.',                ], 422);
            }
        } elseif ($currentRole === 'Doctor') {
            $cancelledVia = 'Doctor';
        } elseif (in_array($currentRole, ['Receptionist', 'Manager'])) {
            $cancelledVia = 'Receptionist';
        } else {
            $cancelledVia = 'Receptionist';
        }

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

    public function markAsSeen(Request $request, $id)
    {

        $appointment = Appointment::where('id', $id)
            ->first();

        if ($appointment) {
            $appointment->update([
                'patient_saw_cancellation' => true,
                'cancellation_seen_at' => now(),
            ]);

            return response()->json(['message' => 'تم التحديث بنجاح']);
        }

        return response()->json(['message' => 'الموعد غير موجود'], 404);
    }

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

        $totalCount = $appointments->count();
        $pendingCount = $pendingAppointments->count();
        $completedCount = $completedAppointments->count();
        $cancelledCount = $cancelledAppointments->count();
        $progressCount = $inProgressAppointments->count();
        $arrivedCount = $arrivedAppointments->count();

        return response()->json([
            'status' => 'success',
            'counts' => [
                'total' => $totalCount,
                'pending' => $pendingCount,
                'completed' => $completedCount,
                'cancelled' => $cancelledCount,
                'in_progress' => $progressCount,
                'arrived' => $arrivedCount,
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

    public function markAsArrived($id)
    {
        $appointment = Appointment::findOrFail($id);

        // تحديث حالة الموعد إلى "حضر" (arrived)
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
     * إدخال المريض للغرفة لبدء الجلسة
     */
    public function startSession($id)
    {
        $appointment = Appointment::findOrFail($id);

        // تحديث حالة الموعد ليكون "قيد العلاج"
        $appointment->update([
            'status' => 'in_progress',
        ]);

        // تحديث حالة الغرفة لتصبح "مشغولة" (busy) تلقائياً
        if ($appointment->room_id) {
            Room::where('id', $appointment->room_id)->update(['status' => 'busy']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم إدخال المريض للغرفة وبدء الجلسة',
            'data' => $appointment,
        ], 200);
    }

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

        // 2. تحديث الخدمات وإرفاقbooked_price
        if ($request->has('treatment_ids') && is_array($request->treatment_ids)) {
            $treatments = Treatment::whereIn('id', $request->treatment_ids)->get();
            $syncData = [];

            foreach ($treatments as $treatment) {
                // جلب السعر (بعد الخصم إن وجد، أو السعر الأساسي)
                $price = $treatment->discount_price ?? $treatment->base_price ?? $treatment->price ?? 0;

                $syncData[$treatment->id] = [
                    'booked_price' => round($price, 2),
                ];
            }

            // استخدام sync مع البيانات الإضافية للجدول الوسيط
            $appointment->treatments()->sync($syncData);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل الموعد بنجاح',
            'data' => $appointment->load('treatments'),
        ]);
    }

    public function validatePromoCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'patient_id' => 'nullable',
        ]);

        // 1. البحث عن كود الخصم في قاعدة البيانات
        $promo = PromoCode::where('code', $request->code)
            ->where('is_active', true) // التأكد أن الكود مفعّل
            ->first();

        if (! $promo) {
            return response()->json([
                'status' => 'error',
                'message' => 'كود الخصم غير صحيح أو غير موجود',
            ], 404);
        }

        // 2. التحقق من تاريخ الصلاحية باستخدام اسم الحقل الصحيح (expiry_date)
        if ($promo->expiry_date && now()->greaterThan($promo->expiry_date)) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، انتهت صلاحية كود الخصم هذا',
            ], 422);
        }

        // 3. إرجاع بيانات الخصم بأسماء الحقول المطابقة تماماً لـ Migration
        return response()->json([
            'status' => 'success',
            'message' => 'تم تطبيق كود الخصم بنجاح!',
            'discount_type' => $promo->discount_type,  // تم تعديلها من type إلى discount_type
            'discount_value' => (float) $promo->discount_value, // تم تعديلها من discount_amount إلى discount_value
        ]);
    }
}
