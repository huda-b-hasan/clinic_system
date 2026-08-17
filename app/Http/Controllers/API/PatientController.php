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

    /**
     * جلب الملف الشخصي للمريض الحالي المسجل دخول عبر الجلسة 
     */
    public function getPatientProfile()
    {
        // 1. التحقق من وجود السيشين
        if (! session()->has('user_id')) {
            return response()->json([
                'message' => ' انتهاء الجلسة',
                'session_all' => session()->all(),
            ], 403);
        }

        if (session('user_role') !== 'Patient') {
            return response()->json(['message' => 'أنت مسجل دخول ولكن ليس بصلاحية مريض، دورك الحالي: '.session('user_role')], 403);
        }

        // 2. البحث عن بيانات المريض المرتبطة بالمستخدم
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

    /**
     * تحديث الملف الشخصي للمريض الحالي
     */
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

        // تحديث جدول المرضى المرتبط بالمستخدم
        $user->patient()->update([
            'gender' => $validatedData['gender'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث ملفك الشخصي بنجاح',
        ], 200);
    }

    /**
     * جلب مواعيد المريض الحالي مع تفاصيل الأطباء، المعالجات، والفواتير
     */
    public function getAppointments()
    {
        if (! session()->has('user_id')) {
            return response()->json(['message' => 'غير مصرح لك، السيشين منتهية'], 403);
        }

        $userId = session('user_id');
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
        }

        $appointments = $patient->appointments()
            ->with([
                'doctor',
                'treatments',
                'clinicSession.bill',
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
     * جلب المعالجات الحديثة التي تحتاج لتقييم (خلال آخر 30 يوماً)
     */
    public function getRecentTreatmentsForRating()
    {
        $userId = session('user_id');
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
                'data' => [],
            ]);
        }

        $recentTreatments = Treatment::whereHas('appointments', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id)
                ->whereHas('clinicSession', function ($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(30));
                });
        })
        ->whereDoesntHave('ratings', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->get();

        $treatmentsArray = $recentTreatments->map(function ($treatment) {
            return [
                'treatment_id' => $treatment->id,
                'treatment_name' => $treatment->name,
            ];
        })->values()->all();

        return response()->json([
            'status' => true,
            'count' => count($treatmentsArray),
            'data' => $treatmentsArray,
        ]);
    }

    /**
     * التحقق مما إذا كان هناك تقييم معلق لم يتم إجراؤه (خلال آخر 7 أيام)
     */
    public function checkPendingRating()
    {
        $userId = session('user_id');
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'status' => false,
                'has_pending' => false,
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
            ]);
        }

        $pendingTreatment = Treatment::whereHas('appointments', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id)
                ->whereHas('clinicSession', function ($q) {
                    $q->where('created_at', '>=', Carbon::now()->subDays(7));
                });
        })
        ->whereDoesntHave('ratings', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->first();

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

    /**
     * جلب بيانات لوحة تحكم المريض (Dashboard) مع الإحصائيات والفواتير غير المدفوعة
     */
    public function getPatientDashboardData()
    {
        if (! session()->has('user_id')) {
            return response()->json(['message' => 'غير مصرح لك، الجلسة منتهية'], 403);
        }

        $userId = session('user_id');
        $patient = Patient::where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json(['message' => 'لم يتم العثور على بيانات المريض'], 404);
        }

        $allAppointments = $patient->appointments()
            ->with(['doctor:id,name', 'treatments:id,name', 'clinicSession.bill'])
            ->orderBy('appointment_date', 'desc')
            ->get();

        // تصفية المواعيد حسب حالتها
        $pendingAppointments = $allAppointments->where('status', 'pending')->values();
        $cancelledAppointments = $allAppointments->where('status', 'canceled')->values();
        $completedAppointments = $allAppointments->where('status', 'completed')
            ->filter(fn($app) => $app->clinicSession !== null)
            ->values();

        // تجميع الفواتير المستحقة (غير المدفوعة)
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
                'pending_appointments' => $pendingAppointments,
                'cancelled_appointments' => $cancelledAppointments,
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


    // ==========================================
    // دوال إدارة المرضى  
    // ==========================================

    /**
     * عرض قائمة جميع المرضى
     */
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

    /**
     * عرض تفاصيل مريض محدد مع حساب المستخدم المرتبط
     */
    public function show($id)
    {
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

    /**
     * إضافة مريض جديد
     */
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

    /**
     * تحديث بيانات مريض محدد
     */
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

    /**
     * البحث عن المرضى بالاسم أو رقم الهاتف
     */
    public function searchPatients(Request $request)
    {
        $query = $request->get('q');

        $patients = Patient::where('name', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->get(['id', 'name', 'phone']);

        return response()->json([
            'status' => 'success',
            'data' => $patients,
        ]);
    }
}