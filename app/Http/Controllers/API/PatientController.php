<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Patient;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;


class PatientController extends Controller
{
public function getPatientProfile()
{
    // 1. فحص هل السيشين موجودة أصلاً أم تضيع؟
    if (! session()->has('user_id')) {
        return response()->json([
            'message' => 'السيشين فارغة تماماً! قد يكون السبب نوع الـ Route أو انتهاء الجلسة',
            'session_all' => session()->all() // سيطبع لك كل محتويات الجلسة الحالية لمعاينتها
        ], 403);
    }

    if (session('user_role') !== 'Patient') {
        return response()->json(['message' => 'أنت مسجل دخول ولكن ليس بصلاحية مريض، دورك الحالي: ' . session('user_role')], 403);
    }

    // 2. فحص الاستعلام
    $userId = session('user_id');
    $patient = Patient::with('user')->where('user_id', $userId)->first();

    if (! $patient) {
        return response()->json([
            'message' => 'السيشين موجودة والـ ID هو ' . $userId . ' ولكن لا يوجد مريض بهذا الرقم في جدول patients',
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'data' => $patient,
    ]);
}

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'gender' => 'required|in:male,female',
        ]);

        // تحديث جدول المستخدمين الأساسي
        $user->update([
            'name' => $validatedData['name'],
            'phone' => $validatedData['phone'],
        ]);

        // تحديث جدول المرضى التابع له
        $user->patient()->update([
            'gender' => $validatedData['gender'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث ملفك الشخصي بنجاح',
        ], 200);
    }

public function getAppointments()
{
    // 1. التحقق من وجود السيشين
    if (!session()->has('user_id')) {
        return response()->json(['message' => 'غير مصرح لك، السيشين منتهية'], 403);
    }

    $userId = session('user_id');
    $patient = Patient::where('user_id', $userId)->first();

    if (!$patient) {
        return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
    }

    // 2. جلب المواعيد مع العلاقات المتداخلة الصحيحة بناءً على الـ Migrons
    // الموعد يملك طبيب (doctor)، وعلاجات (treatments)
    // ويملك جلسة (clinicSession) والجلسة هي التي تملك الفاتورة (clinicSession.bill)
    $appointments = $patient->appointments()
        ->with([
            'doctor', 
            'treatments', 
            'clinicSession.bill' // جلب الجلسة والفاتورة التابعة لها بأمان
        ]) 
        ->orderBy('appointment_date', 'desc')
        ->get();

    return response()->json([
        'status' => 'success',
        'count' => $appointments->count(),
        'data' => $appointments,
    ], 200);
}

/**
 * جلب إحصائيات المريض: المواعيد القادمة، الجلسات السابقة، والفواتير المستحقة بدالة واحدة
 */
public function getPatientDashboardData()
{
    // 1. التحقق من السيشين اليدوية
    if (!session()->has('user_id')) {
        return response()->json(['message' => 'غير مصرح لك، الجلسة منتهية'], 403);
    }

    $userId = session('user_id');
    $patient = Patient::where('user_id', $userId)->first();

    if (!$patient) {
        return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
    }

    // الوقت الحالي للمقارنة (سنة 2026)
    $now = Carbon::now();

    // 2. جلب المواعيد القادمة أو المعلقة (حالتها pending وتاريخها مستقبلي أو اليوم)
    $futureAppointments = $patient->appointments()
        ->where(function($query) use ($now) {
            $query->where('status', 'pending')
                  ->orWhere('appointment_date', '>=', $now);
        })
        ->with(['doctor:id,name', 'treatments:id,name']) // جلب الحقول المحتاجة فقط لتسريع الأداء
        ->orderBy('appointment_date', 'asc')
        ->get();

    // 3. جلب الجلسات السابقة (التي أنشئ لها سجل في جدول clinic_sessions)
    // ندخل من المريض للمواعيد المكتملة، ثم نجلب الجلسة والفاتورة بداخلها
    $pastSessions = $patient->appointments()
        ->where('status', 'completed')
        ->whereHas('clinicSession') // نضمن أن لها جلسة مسجلة فعلياً
        ->with(['doctor:id,name', 'treatments:id,name', 'clinicSession.bill'])
        ->orderBy('appointment_date', 'desc')
        ->get();

    // 4. جلب وتحليل الفواتير المستحقة (غير المدفوعة)
    // نمر عبر المواعيد المكتملة التي تحتوي على جلسات بها فواتير غير مدفوعة
    $unpaidBills = [];
    $totalUnpaidAmount = 0;

    foreach ($pastSessions as $appointment) {
        $session = $appointment->clinicSession;
        if ($session && $session->bill && $session->bill->status === 'unpaid') {
            $unpaidBills[] = [
                'session_id' => $session->id,
                'appointment_date' => $appointment->appointment_date,
                'treatment' => $appointment->treatments->first() ? $appointment->treatments->first()->name : 'جلسة علاجية',
                'doctor_name' => $appointment->doctor ? $appointment->doctor->name : 'غير محدد',
                'amount' => $session->bill->amount_paid,
                'bill_date' => $session->bill->date,
            ];
            $totalUnpaidAmount += (float) $session->bill->amount_paid;
        }
    }

    // 5. تجميع كل البيانات والعدادات في مصفوفة واحدة نظيفة للفرونت إند
    return response()->json([
        'status' => 'success',
        'stats' => [
            'future_appointments_count' => $futureAppointments->count(),
            'past_sessions_count'       => $pastSessions->count(),
            'unpaid_bills_count'       => count($unpaidBills),
            'total_unpaid_amount'      => $totalUnpaidAmount,
        ],
        'data' => [
            'future_appointments' => $futureAppointments,
            'past_sessions'       => $pastSessions->map(function($app) {
                return [
                    'session_id'       => $app->clinicSession->id,
                    'appointment_date' => $app->appointment_date,
                    'doctor_name'      => $app->doctor ? $app->doctor->name : 'غير محدد',
                    'treatments'       => $app->treatments,
                    'doctor_notes'     => $app->clinicSession->doctor_notes,
                    'bill'             => $app->clinicSession->bill
                ];
            }),
            'unpaid_bills' => $unpaidBills
        ]
    ], 200);
}
}
