<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\PromoCode;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller{

// public function storeAppointment(Request $request)
// {
//     // 1. التحقق من البيانات المدخلة (Validation)
//     $request->validate([
//         'patient_id' => 'required|exists:patients,id',
//         'user_id' => 'required_without:auth_id', 
//         'doctor_id' => 'required|exists:users,id',
//         'room_id' => 'required|exists:rooms,id',
//         'appointment_date' => 'required|date_format:Y-m-d H:i:s',
//         'treatment_ids' => 'required|array',
//         'treatment_ids.*' => 'exists:treatments,id',
//         'promo_code' => 'nullable|string', // حقل كود الخصم اختياري
//     ]);

//     // 2. فحص فواتير المريض المعلقة (Unpaid Bills Check)
//     $hasUnpaidBills = DB::table('bills')
//         ->join('clinic_sessions', 'bills.clinic_session_id', '=', 'clinic_sessions.id')
//         ->join('appointments', 'clinic_sessions.appointment_id', '=', 'appointments.id')
//         ->where('appointments.patient_id', $request->patient_id)
//         ->where('bills.status', 'unpaid')
//         ->exists();

//     if ($hasUnpaidBills) {
//         return response()->json([
//             'status' => false,
//             'message' => 'عذراً، لا يمكن إتمام الحجز لوجود فواتير مستحقة وغير مدفوعة على هذا المريض من جلسات سابقة.',
//         ], 422);
//     }

//     // 3. التحقق من صحة كود الخصم في حال إرساله (Promo Code Validation Logic)
//     $promoCode = null;
//     if ($request->filled('promo_code')) {
//         // البحث عن الكود في قاعدة البيانات
//         $promoCode = PromoCode::where('code', $request->promo_code)->first();

//         // أ. التحقق من وجود الكود
//         if (!$promoCode) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'كود الخصم المدخل غير صحيح أو غير موجود.',
//             ], 422);
//         }

//         // ب. التحقق من حالة تفعيل الكود
//         if (!$promoCode->is_active) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'عذراً، هذا الكود غير نشط حالياً ولا يمكن استخدامه.',
//             ], 422);
//         }

//         // ج. التحقق من تاريخ الصلاحية
//         if (Carbon::parse($promoCode->expiry_date)->isPast()) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'عذراً، انتهت صلاحية استخدام هذا الكود.',
//             ], 422);
//         }

//         // د. التحقق من الحد الأقصى للاستخدام العام للعيادة
//         if ($promoCode->usage_limit !== null && $promoCode->used_count >= $promoCode->usage_limit) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'عذراً، نفدت الكمية المتاحة لاستخدام هذا الكود.',
//             ], 422);
//         }

//         // هـ. التحقق من عدم استخدام المريض للكود مسبقاً (مرة واحدة فقط لكل مريض)
//         $alreadyUsed = DB::table('promo_code_patient')
//             ->where('patient_id', $request->patient_id)
//             ->where('promo_code_id', $promoCode->id)
//             ->exists();

//         if ($alreadyUsed) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'لقد قمت باستخدام هذا الكود من قبل، لا يمكن استخدام الكود إلا مرة واحدة للمريض الواحد.',
//             ], 422);
//         }

//         // و. التحقق من ارتباط الكود بخدمة معينة
//         if ($promoCode->treatment_id !== null) {
//             if (!in_array($promoCode->treatment_id, $request->treatment_ids)) {
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'هذا الكود مخصص لخدمة علاجية معينة وغير صالح للخدمات المحددة في هذا الحجز.',
//                 ], 422);
//             }
//         }
//     }

//     // 4. حساب وقت البدء ووقت الانتهاء المتوقع بناءً على مدة المعالجات
//     $startTime = Carbon::parse($request->appointment_date);
//     $totalDuration = (int) Treatment::whereIn('id', $request->treatment_ids)->sum('duration');
//     $endTime = $startTime->copy()->addMinutes($totalDuration);

//     $startStr = $startTime->toDateTimeString();
//     $endStr = $endTime->toDateTimeString();

//     // 5. فحص تعارض الطبيب
//     $doctorBusy = Appointment::where('doctor_id', $request->doctor_id)
//         ->where(function ($query) use ($startStr, $endStr) {
//             $query->whereHas('treatments', function ($q) use ($endStr) {
//                 $q->where('appointments.appointment_date', '<', $endStr);
//             })->where(DB::raw('DATE_ADD(appointments.appointment_date, INTERVAL (SELECT SUM(duration) FROM treatments JOIN appointment_treatment ON treatments.id = appointment_treatment.treatment_id WHERE appointment_treatment.appointment_id = appointments.id) MINUTE)'), '>', $startStr);
//         })->exists();

//     if ($doctorBusy) {
//         return response()->json([
//             'status' => false,
//             'message' => 'الطبيب مشغول في هذا الوقت، يرجى اختيار وقت آخر.',
//         ], 422);
//     }

//     // 6. فحص تعارض الغرفة
//     $roomBusy = Appointment::where('room_id', $request->room_id)
//         ->where(function ($query) use ($startStr, $endStr) {
//             $query->where('appointment_date', '<', $endStr)
//                 ->where(DB::raw('DATE_ADD(appointment_date, INTERVAL (SELECT SUM(duration) FROM treatments JOIN appointment_treatment ON treatments.id = appointment_treatment.treatment_id WHERE appointment_treatment.appointment_id = appointments.id) MINUTE)'), '>', $startStr);
//         })->exists();

//     if ($roomBusy) {
//         return response()->json([
//             'status' => false,
//             'message' => 'الغرفة المختارة محجوزة لحساب موعد آخر في هذا الوقت.',
//         ], 422);
//     }

//     // 7. فحص تعارض الأجهزة الطبية المرتبطة بالطلب
//     $requiredDeviceIds = DB::table('device_treatment')
//         ->whereIn('treatment_id', $request->treatment_ids)
//         ->pluck('device_id')
//         ->toArray();

//     if (!empty($requiredDeviceIds)) {
//         $deviceConflict = Appointment::where('appointment_date', '<', $endStr)
//             ->where(DB::raw('DATE_ADD(appointment_date, INTERVAL (SELECT SUM(duration) FROM treatments JOIN appointment_treatment ON treatments.id = appointment_treatment.treatment_id WHERE appointment_treatment.appointment_id = appointments.id) MINUTE)'), '>', $startStr)
//             ->whereHas('treatments.devices', function ($query) use ($requiredDeviceIds) {
//                 $query->whereIn('devices.id', $requiredDeviceIds);
//             })->exists();

//         if ($deviceConflict) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'الأجهزة الطبية اللازمة لهذه المعالجة مستخدمة حالياً في موعد آخر.',
//             ], 422);
//         }
//     }

//     // 8. إنشاء الموعد وتطبيق الخصم داخل الـ Database Transaction للأمان وثبات البيانات
//     DB::beginTransaction();
//     try {
//         // إنشاء الموعد الأساسي
//         $appointment = Appointment::create([
//             'patient_id' => $request->patient_id,
//             'doctor_id' => $request->doctor_id,
//             'user_id' => auth()->id() ?: $request->user_id, 
//             'room_id' => $request->room_id,
//             'appointment_date' => $startStr,
//             'status' => 'pending',
//         ]);

//         // جلب أسعار المعالجات لتثبيتها وقت الحجز
//         $treatments = Treatment::whereIn('id', $request->treatment_ids)->get();
//         $syncData = [];

//         foreach ($treatments as $treatment) {
//             // السعر المعتمد قبل تطبيق كود الخصم (السعر الأساسي أو سعر العرض النشط)
//             $finalPrice = $treatment->discount_price ?? $treatment->base_price;
//             $appliedPromoId = null;

//             // تطبيق خصم الكود بناءً على نوعه (محدد للخدمة أم عام)
//             if ($promoCode) {
//                 // الحالة الأولى: الكود مخصص لخدمة معينة وتطابق مع الخدمة الحالية
//                 // الحالة الثانية: الكود عام (treatment_id هو null) فيُطبق على جميع الخدمات
//                 if ($promoCode->treatment_id == $treatment->id || $promoCode->treatment_id === null) {
                    
//                     $appliedPromoId = $promoCode->id; // تثبيت الـ ID للربط في الجدول الوسيط

//                     if ($promoCode->discount_type === 'percentage') {
//                         // خصم النسبة المئوية
//                         $finalPrice = $finalPrice - ($finalPrice * ($promoCode->discount_value / 100));
//                     } elseif ($promoCode->discount_type === 'fixed') {
//                         // خصم القيمة الثابتة
//                         $finalPrice = $finalPrice - $promoCode->discount_value;
//                     }

//                     // حماية السعر: التأكد من أن السعر لا يصبح سالباً تحت أي ظرف
//                     if ($finalPrice < 0) {
//                         $finalPrice = 0.00;
//                     }
//                 }
//             }

//             // تجهيز البيانات للحفظ في الجدول الوسيط appointment_treatment
//             $syncData[$treatment->id] = [
//                 'booked_price' => round($finalPrice, 2),
//                 'promo_code_id' => $appliedPromoId
//             ];
//         }

//         // ربط الخدمات بالموعد في الجدول الوسيط مع حفظ الأسعار وأكواد الخصم المرتبطة بها
//         $appointment->treatments()->attach($syncData);

//         // إذا تم استخدام كود الخصم بنجاح، نقوم بتحديث الـ Logs وجداول الكود
//         if ($promoCode) {
//             // أ. زيادة عداد الاستخدام الإجمالي للكود
//             $promoCode->increment('used_count');

//             // ب. تسجيل استخدام المريض للكود لمنعه من التكرار مستقبلاً
//             DB::table('promo_code_patient')->insert([
//                 'patient_id' => $request->patient_id,
//                 'promo_code_id' => $promoCode->id,
//                 'used_at' => Carbon::now()
//             ]);
//         }

//         DB::commit();

//         return response()->json([
//             'status' => true,
//             'message' => 'تم تسجيل الموعد بنجاح. .',
//             'data' => $appointment->load('treatments'),
//         ], 201);

//     } catch (\Exception $e) {
//         DB::rollBack();

//         return response()->json([
//             'status' => false,
//             'message' => 'حدث خطأ غير متوقع أثناء الحجز: ' . $e->getMessage(),
//         ], 500);
//     }
// }
public function storeAppointment(Request $request)
{
    // 1. التحقق من البيانات المدخلة (Validation)
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

    // 2. فحص فواتير المريض المعلقة (Unpaid Bills Check) عبر Eloquent
    $hasUnpaidBills = Appointment::where('patient_id', $request->patient_id)
        ->whereHas('clinicSession.bill', function ($query) {
            $query->where('status', 'unpaid');
        })->exists();

    if ($hasUnpaidBills) {
        return response()->json([
            'status' => false,
            'message' => 'عذراً، لا يمكن إتمام الحجز لوجود فواتير مستحقة وغير مدفوعة على هذا المريض من جلسات سابقة.',
        ], 422);
    }

    // 3. التحقق من صحة كود الخصم في حال إرساله (Promo Code Validation Logic)
    $promoCode = null;
    if ($request->filled('promo_code')) {
        $promoCode = PromoCode::where('code', $request->promo_code)->first();

        if (!$promoCode) {
            return response()->json([ 'status' => false, 'message' => 'كود الخصم المدخل غير صحيح أو غير موجود.' ], 422);
        }

        if (!$promoCode->is_active) {
            return response()->json([ 'status' => false, 'message' => 'عذراً، هذا الكود غير نشط حالياً ولا يمكن استخدامه.' ], 422);
        }

        if (Carbon::parse($promoCode->expiry_date)->isPast()) {
            return response()->json([ 'status' => false, 'message' => 'عذراً، انتهت صلاحية استخدام هذا الكود.' ], 422);
        }

        if ($promoCode->usage_limit !== null && $promoCode->used_count >= $promoCode->usage_limit) {
            return response()->json([ 'status' => false, 'message' => 'عذراً، نفدت الكمية المتاحة لاستخدام هذا الكود.' ], 422);
        }

        // فحص استخدام المريض المسبق عبر علاقة Eloquent أو الجدول الوسيط
        $alreadyUsed = DB::table('promo_code_patient') // هذا جدول وسيط بسيط (لا بأس بـ DB هنا أو يمكنك إنشاء موديل له)
            ->where('patient_id', $request->patient_id)
            ->where('promo_code_id', $promoCode->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json([ 'status' => false, 'message' => 'لقد قمت باستخدام هذا الكود من قبل، لا يمكن استخدامه إلا مرة واحدة للمريض الواحد.' ], 422);
        }

        if ($promoCode->treatment_id !== null && !in_array($promoCode->treatment_id, $request->treatment_ids)) {
            return response()->json([ 'status' => false, 'message' => 'هذا الكود مخصص لخدمة علاجية معينة وغير صالح للخدمات المحددة في هذا الحجز.' ], 422);
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

    // جلب جميع مواعيد اليوم المختار (لتجنب عمل Queries معقدة، سنقوم بالفحص برمجياً)
    $existingAppointments = Appointment::with('treatments')
        ->whereDate('appointment_date', $startTime->toDateString())
        ->get();

    // دالة داخلية لمقارنة الأوقات ومعرفة ما إذا كان الموعد الجديد يتعارض مع موعد قائم
    $hasConflict = function($existingApp) use ($startTime, $endTime) {
        $appStart = Carbon::parse($existingApp->appointment_date);
        $appDuration = $existingApp->treatments->sum('duration');
        $appEnd = $appStart->copy()->addMinutes($appDuration);

        // شرط التداخل بين فترتين زمنيتين: (Start1 < End2) و (End1 > Start2)
        return $startTime->lessThan($appEnd) && $endTime->greaterThan($appStart);
    };

    // 5. فحص تعارض الطبيب
    $doctorBusy = $existingAppointments
        ->where('doctor_id', $request->doctor_id)
        ->contains($hasConflict);

    if ($doctorBusy) {
        return response()->json([ 'status' => false, 'message' => 'الطبيب مشغول في هذا الوقت، يرجى اختيار وقت آخر.' ], 422);
    }

    // 6. فحص تعارض الغرفة
    $roomBusy = $existingAppointments
        ->where('room_id', $request->room_id)
        ->contains($hasConflict);

    if ($roomBusy) {
        return response()->json([ 'status' => false, 'message' => 'الغرفة المختارة محجوزة لحساب موعد آخر في هذا الوقت.' ], 422);
    }

    // 7. فحص تعارض الأجهزة الطبية المرتبطة بالطلب
    $requiredDeviceIds = Treatment::whereIn('id', $request->treatment_ids)
        ->with('devices')
        ->get()
        ->pluck('devices.*.id')
        ->flatten()
        ->unique()
        ->toArray();

    if (!empty($requiredDeviceIds)) {
        // نمر على كل المواعيد المتداخلة ونرى إن كانت تستخدم نفس الأجهزة
        $deviceConflict = $existingAppointments->filter($hasConflict)->contains(function ($appointment) use ($requiredDeviceIds) {
            $currentAppDeviceIds = $appointment->treatments
                ->loadMissing('devices')
                ->pluck('devices.*.id')
                ->flatten()
                ->unique()
                ->toArray();
                
            return !empty(array_intersect($requiredDeviceIds, $currentAppDeviceIds));
        });

        if ($deviceConflict) {
            return response()->json([ 'status' => false, 'message' => 'الأجهزة الطبية اللازمة لهذه المعالجة مستخدمة حالياً في موعد آخر.' ], 422);
        }
    }

    // 8. إنشاء الموعد وتطبيق الخصم داخل الـ Transaction
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

                    if ($finalPrice < 0) { $finalPrice = 0.00; }
                }
            }

            $syncData[$treatment->id] = [
                'booked_price' => round($finalPrice, 2),
                'promo_code_id' => $appliedPromoId
            ];
        }

        $appointment->treatments()->attach($syncData);

        if ($promoCode) {
            $promoCode->increment('used_count');

            DB::table('promo_code_patient')->insert([
                'patient_id' => $request->patient_id,
                'promo_code_id' => $promoCode->id,
                'used_at' => Carbon::now()
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
        return response()->json([ 'status' => false, 'message' => 'حدث خطأ غير متوقع: ' . $e->getMessage() ], 500);
    }
}
public function cancelAppointment($id)
    {
        // 1. البحث عن الموعد أو إرجاع خطأ 404 إذا مش موجود
        $appointment = Appointment::findOrFail($id);

        // 2. التحقق من أن حالة الموعد الحالية هي قيد الانتظار فقط
        if ($appointment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الموعد لأنه تم معالجته مسبقاً أو ملغي بالفعل.'
            ], 400);
        }

        // 3. تعديل الحالة إلى ملغي وحفظ التعديل
        $appointment->status = 'canceled';
        $appointment->save();

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الموعد بنجاح.'
        ], 200);
    }
    // public function store(Request $request)
    // {
    //     // 1. المدخلات القادمة من المستخدم (فقط العلاج، الطبيب، والوقت)
    //     $request->validate([
    //         'patient_id' => 'required|exists:patients,id',
    //         'treatment_id' => 'required|exists:treatments,id',
    //         'doctor_id' => 'required|exists:users,id',
    //         'appointment_date' => 'required|date_format:Y-m-d H:i:s',
    //     ]);

    //     // 2. جلب تفاصيل العلاج والوقت
    //     $treatment = Treatment::findOrFail($request->treatment_id);
    //     $startTime = Carbon::parse($request->appointment_date);
    //     $endTime = $startTime->copy()->addMinutes($treatment->duration);

    //     // 3. جلب الغرفة والجهاز المرتبطين بهذا العلاج تلقائياً من العلاقات
    //     // (حسب الجداول الوسيطة في المخطط الخاص بكِ)
    //     $roomId = $treatment->room_id; // أو بالطريقة التي ربطتي بها الغرفة بالعلاج
    //     $deviceId = $treatment->devices()->first()?->id; // جلب أول جهاز مرتبط بهذا العلاج مثلاً

    //     // 4. دالة فحص التقاطع (Overlap) الثابتة
    //     $checkOverlap = function ($query) use ($startTime, $endTime) {
    //         $query->where(function ($q) use ($startTime, $endTime) {
    //             $q->where('oppintment_date', '<', $endTime)
    //                 ->where(\DB::raw('DATE_ADD(oppintment_date, INTERVAL (SELECT duration FROM treatments WHERE treatments.id = appointments.treatment_id) MINUTE)'), '>', $startTime);
    //         });
    //     };

    //     // 5. الفحص الأول: هل الطبيب مشغول؟
    //     $doctorBusy = Appointment::where('doctor_id', $request->doctor_id)
    //         ->where($checkOverlap)
    //         ->exists();

    //     if ($doctorBusy) {
    //         return response()->json(['status' => 'error', 'message' => 'الطبيب غير متاح في هذا الوقت.'], 422);
    //     }

    //     // 6. الفحص الثاني: هل الغرفة المطلوبة لهذا العلاج محجوزة؟
    //     if ($roomId) {
    //         $roomBusy = Appointment::where('room_id', $roomId)
    //             ->where($checkOverlap)
    //             ->exists();

    //         if ($roomBusy) {
    //             return response()->json(['status' => 'error', 'message' => 'الغرفة المطلوبة لهذا العلاج غير متاحة في هذا الوقت.'], 422);
    //         }
    //     }

    //     // 7. الفحص الثالث: هل الجهاز المطلوب لهذا العلاج مشغول؟
    //     if ($deviceId) {
    //         // هنا نفحص إذا كان هناك حجز آخر في نفس الوقت يستخدم غرفة تحتوي على هذا الجهاز،
    //         // أو حجز لنفس الجهاز (حسب طريقة ربط الحجوزات بالأجهزة عندك)
    //         $deviceBusy = Appointment::where('room_id', $roomId) // الفحص الافتراضي بناءً على الغرفة والجهاز
    //             ->where($checkOverlap)
    //             ->exists();

    //         if ($deviceBusy) {
    //             return response()->json(['status' => 'error', 'message' => 'الجهاز اللازم لهذا العلاج مستخدم حالياً.'], 422);
    //         }
    //     }

    //     // 8. إذا كل شيء متاح -> يتم الحجز تلقائياً وتخزين الغرفة التي تم سحبها
    //     $appointment = Appointment::create([
    //         'patient_id' => $request->patient_id,
    //         'doctor_id' => $request->doctor_id,
    //         'treatment_id' => $request->treatment_id,
    //         'oppintment_date' => $startTime,
    //         'room_id' => $roomId, // تم جلبه تلقائياً
    //         'status' => 'pending',
    //         'user_id' => auth()->id() ?? $request->user_id,
    //     ]);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'تم تسجيل الموعد بنجاح، وتخصيص الغرفة والجهاز المطلوبين تلقائياً!',
    //         'data' => $appointment,
    //     ], 201);
    // }
}
