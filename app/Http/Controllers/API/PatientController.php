<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function getPatientProfile()
    {
        // 1. فحص هل السيشين موجودة أصلاً أم تضيع؟
        if (! session()->has('user_id')) {
            return response()->json([
                'message' => 'السيشين فارغة تماماً! قد يكون السبب نوع الـ Route أو انتهاء الجلسة',
                'session_all' => session()->all(), // سيطبع لك كل محتويات الجلسة الحالية لمعاينتها
            ], 403);
        }

        if (session('user_role') !== 'Patient') {
            return response()->json(['message' => 'أنت مسجل دخول ولكن ليس بصلاحية مريض، دورك الحالي: '.session('user_role')], 403);
        }

        // 2. فحص الاستعلام
        $userId = session('user_id');
        $patient = Patient::with('user')->where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'message' => 'السيشين موجودة والـ ID هو '.$userId.' ولكن لا يوجد مريض بهذا الرقم في جدول patients',
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
        if (! session()->has('user_id')) {
            return response()->json(['message' => 'غير مصرح لك، السيشين منتهية'], 403);
        }

        $userId = session('user_id');
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
        }

        // 2. جلب المواعيد مع العلاقات المتداخلة الصحيحة بناءً على الـ Migrons
        // الموعد يملك طبيب (doctor)، وعلاجات (treatments)
        // ويملك جلسة (clinicSession) والجلسة هي التي تملك الفاتورة (clinicSession.bill)
        $appointments = $patient->appointments()
            ->with([
                'doctor',
                'treatments',
                'clinicSession.bill', // جلب الجلسة والفاتورة التابعة لها بأمان
            ])
            ->orderBy('appointment_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $appointments->count(),
            'data' => $appointments,
        ], 200);
    }

    public function getRecentTreatmentsForRating()
    {
        // 1. جلب معرف المستخدم من السيشين
        $userId = session('user_id');

        // 2. جلب المريض المرتبط بهذا المستخدم
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
                'data' => [],
            ]);
        }

        // 3. الاستعلام عن كل الخدمات الفريدة التي تمت جلساتها خلال آخر 30 يوماً ولم تُقيّم بعد
        $recentTreatments = Treatment::whereHas('appointments', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id)
                ->whereHas('clinicSession', function ($q) {
                    // تمت الجلسة خلال آخر 30 يوماً (شهر أو أقل)
                    $q->where('created_at', '>=', Carbon::now()->subDays(30));
                });
        })
        // التأكد من أن المستخدم الحالي لم يقم بتقييم هذه الخدمات مسبقاً
            ->whereDoesntHave('ratings', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();

        // 4. تحويل مجموعة البيانات إلى مصفوفة مبسطة (id و name) وإرجاعها
        $treatmentsArray = $recentTreatments->map(function ($treatment) {
            return [
                'treatment_id' => $treatment->id,
                'treatment_name' => $treatment->name,
            ];
        })->values()->all();

        return response()->json([
            'status' => true,
            'count' => count($treatmentsArray),
            'data' => $treatmentsArray, // مصفوفة الخدمات الجاهزة للتقييم
        ]);
    }

    public function checkPendingRating()
    {
        // 1. جلب معرف المستخدم من السيشين التي قمتِ بالتحقق منها في الميدل وير
        $userId = session('user_id');

        // 2. جلب المريض المرتبط بهذا المستخدم
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'status' => false,
                'has_pending' => false,
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
            ]);
        }

        // 3. الاستعلام الحديث (Eloquent ORM) للبحث عن معالجة تحتاج لتقييم
        $pendingTreatment = Treatment::whereHas('appointments', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id)
                ->whereHas('clinicSession', function ($q) {
                    // تمت الجلسة خلال آخر 7 أيام
                    $q->where('created_at', '>=', Carbon::now()->subDays(7));
                });
        })
        // التأكد من أن المستخدم الحالي لم يقم بتقييم هذه المعالجة مسبقاً
            ->whereDoesntHave('ratings', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();

        // 4. إرجاع النتيجة للـ Front-end
        if ($pendingTreatment) {
            return response()->json([
                'status' => true,
                'has_pending' => true,
                'data' => [
                    'treatment_id' => $pendingTreatment->id,
                    'treatment_name' => $pendingTreatment->name,
                ],
            ]);
        }

        return response()->json([
            'status' => true,
            'has_pending' => false,
        ]);
    }

    public function getPatientDashboardData()
    {
        // 1. التحقق من السيشين اليدوية
        if (! session()->has('user_id')) {
            return response()->json(['message' => 'غير مصرح لك، الجلسة منتهية'], 403);
        }

        $userId = session('user_id');
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
        }

        // جلب جميع مواعيد المريض مع العلاقات المطلوبة وترتيبها من الأحدث للأقدم
        $allAppointments = $patient->appointments()
            ->with(['doctor:id,name', 'treatments:id,name', 'clinicSession.bill'])
            ->orderBy('appointment_date', 'desc')
            ->get();

        // 2. تقسيم المواعيد بناءً على الحالات المطلوبة باستخدام Collection Filtering

        // أ. المواعيد المعلقة (Pending)
        $pendingAppointments = $allAppointments->where('status', 'pending')->values();

        // ب. المواعيد الملغاة (Cancelled) - تأكد أن التسمية في الـ Enum تطابق القيمة هنا
        $cancelledAppointments = $allAppointments->where('status', 'canceled')->values();

        // ج. الجلسات المكتملة (Completed) والتي تمتلك جلسة مسجلة فعلياً
        $completedAppointments = $allAppointments->where('status', 'completed')
            ->filter(function ($app) {
                return $app->clinicSession !== null;
            })
            ->values();

        // 3. تحليل وفحص الفواتير المستحقة (غير المدفوعة) من المواعيد المكتملة
        $unpaidBills = [];
        $totalUnpaidAmount = 0;

        foreach ($completedAppointments as $appointment) {
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

        $unreadCancellations = $allAppointments
            ->where('status', 'canceled')
            ->where('patient_saw_cancellation', false)
            ->values();

        return response()->json([
            'status' => 'success',
            'stats' => [
                'pending_appointments_count' => $pendingAppointments->count(),
                'cancelled_appointments_count' => $cancelledAppointments->count(),
                'completed_sessions_count' => $completedAppointments->count(),
                'unpaid_bills_count' => count($unpaidBills),
                'total_unpaid_amount' => $totalUnpaidAmount,
            ],
            'data' => [
                // المواعيد المعلقة
                'pending_appointments' => $pendingAppointments,

                // المواعيد الملغاة
                'cancelled_appointments' => $cancelledAppointments,

                // المواعيد المكتملة (مهيأة ومحسنة العرض)
                'completed_appointments' => $completedAppointments->map(function ($app) {
                    return [
                        'session_id' => $app->clinicSession->id,
                        'appointment_date' => $app->appointment_date,
                        'doctor_name' => $app->doctor ? $app->doctor->name : 'غير محدد',
                        'treatments' => $app->treatments,
                        'doctor_notes' => $app->clinicSession->doctor_notes,
                        'bill' => $app->clinicSession->bill,
                    ];
                }),

                // الفواتير غير المدفوعة
                'unread_cancellations' => $unreadCancellations->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'treatment' => $item->treatments->first()?->name ?? 'جلسة',
                        'cancelled_via' => $item->cancelled_via,
                        'cancellation_reason' => $item->cancellation_reason,
                    ];
                }),
            ],

        ], 200);
    }
    // ////////////////////////////////////////////////////////////////////////

    public function index()
    {
        $patients = Patient::select('id', 'name', 'phone', 'gender', 'birthdate', 'address', 'medical_notes')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $patients->count(),
            'data' => $patients,
        ], 200);
    }

    public function show($id)
    {
        // جلب المريض المحدد مع بيانات حسابه المرتبط (User)
        $patient = Patient::with('user:id,name,email,phone')->find($id);

        if (! $patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'المريض غير موجود في النظام',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $patient,
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'gender' => 'required|in:male,female',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'medical_notes' => 'nullable|string',
        ]);

        $patient = Patient::create($validated);

        return response()->json([
            'message' => 'تم حفظ المريض بنجاح',
            'data' => $patient,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::find($id);

        if (! $patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'المريض غير موجود',
            ], 404);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'gender' => 'required|in:male,female',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'medical_notes' => 'nullable|string',
        ]);

        $patient->update($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث بيانات المريض بنجاح',
            'data' => $patient,
        ], 200);
    }
}
