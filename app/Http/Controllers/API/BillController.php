<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;


class BillController extends Controller
{
public function getBillDataPatient(Request $request)
{
    $userId = session('user_id');

    $patient = \DB::table('patients')->where('user_id', $userId)->first();

    if (!$patient) {
        return response()->json([
            'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.'
        ], 404);
    }
    
    $bills = \DB::table('bills')
        ->join('clinic_sessions', 'bills.clinic_session_id', '=', 'clinic_sessions.id')
        ->join('appointments', 'clinic_sessions.appointment_id', '=', 'appointments.id')
        ->where('appointments.patient_id', $patient->id)
        ->select('bills.*', 'clinic_sessions.appointment_id')
        ->get();

    $totalPaid = $bills->where('status', 'paid')->sum('amount_paid');

    $totalPending = $bills->whereIn('status', ['unpaid', 'partially_paid'])->sum('amount_paid');

    $formattedInvoices = $bills->map(function ($bill) {
        
        $sessionName = 'جلسة علاجية'; 
        
        $treatmentIds = \DB::table('appointment_treatment')
            ->where('appointment_id', $bill->appointment_id)
            ->pluck('treatment_id')
            ->toArray();
            
        if (!empty($treatmentIds)) {
            $treatments = \DB::table('treatments')
                ->whereIn('id', $treatmentIds)
                ->get();
                
            $matchedTreatment = $treatments->first(function ($treatment) use ($bill) {
                return (float)$treatment->discount_price == (float)$bill->amount_paid || 
                       (float)$treatment->base_price == (float)$bill->amount_paid;
            });
            
            if ($matchedTreatment) {
                $sessionName = $matchedTreatment->name;
            } else {
                $allBillsForThisAppointment = \DB::table('bills')
                    ->join('clinic_sessions', 'bills.clinic_session_id', '=', 'clinic_sessions.id')
                    ->where('clinic_sessions.appointment_id', $bill->appointment_id)
                    ->orderBy('bills.id', 'asc')
                    ->pluck('bills.id')
                    ->toArray();
                    
                $currentBillIndex = array_search($bill->id, $allBillsForThisAppointment);
                
                if ($currentBillIndex !== false && isset($treatments[$currentBillIndex])) {
                    $sessionName = $treatments[$currentBillIndex]->name;
                } else {
                    $sessionName = $treatments->first()->name ?? 'جلسة علاجية';
                }
            }
        }

        return [
            'invoice_number' => 'B-' . str_pad($bill->id, 4, '0', STR_PAD_LEFT) . '#',
            'session_name'   => $sessionName, 
            'amount'         => number_format($bill->amount_paid, 2) . ' ل.س',
            'date'           => $bill->date,
            'status'         => $bill->status == 'paid' ? 'مدفوعة' : 'غير مدفوعة', 
            'raw_status'     => $bill->status 
        ];
    });

    return response()->json([
        'summary' => [
            'total_paid' => number_format($totalPaid, 2) . ' ل.س',
            'total_pending' => number_format($totalPending, 2) . ' ل.س',
        ],
        'invoices' => $formattedInvoices
    ], 200);
}
public function getPendingBillsCount(Request $request)
{
    $userId = session('user_id');

    // 1. جلب سجل المريض المرتبط بالحساب الحالي
    $patient = \DB::table('patients')->where('user_id', $userId)->first();

    if (!$patient) {
        return response()->json([
            'status' => false,
            'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.'
        ], 404);
    }

    // 2. حساب عدد الفواتير المعلقة مباشرة باستخدام count() لسرعة الأداء
    $pendingCount = \DB::table('bills')
        ->join('clinic_sessions', 'bills.clinic_session_id', '=', 'clinic_sessions.id')
        ->join('appointments', 'clinic_sessions.appointment_id', '=', 'appointments.id')
        ->where('appointments.patient_id', $patient->id)
        ->whereIn('bills.status', ['unpaid', 'partially_paid']) // الفواتير المعلقة
        ->count(); // إرجاع العدد فقط دون تحميل السجلات كاملة

    return response()->json([
        'status' => true,
        'pending_bills_count' => $pendingCount
    ], 200);
}
//   public function index()
//     {
//         // جلب كل الفواتير مع تحميل العلاقات كرمال الأداء (Eager Loading) ولنجيب اسم المريض
//         $bills = Bill::with(['clinicSession.appointment.patient'])->get();

//         // حساب الإجماليات الكلية لكل العيادة
//         $totalPaid = $bills->where('status', 'paid')->sum('amount_paid');
//         $totalPending = $bills->where('status', 'unpaid')->sum('amount_paid');

//         // تنسيق البيانات لتظهر كاملة وبشكل مرتب في جدول الأدمن
//         $formattedInvoices = $bills->map(function ($bill) {
//             return [
//                 'id' => $bill->id,
//                 'invoice_number' => 'B-' . str_pad($bill->id, 4, '0', STR_PAD_LEFT) . '#',
//                 'session_name' => 'S-' . str_pad($bill->clinic_session_id, 3, '0', STR_PAD_LEFT) . '#',
//                 'patient_name' => $bill->clinicSession->appointment->patient->name ?? 'غير معروف', // مضاف للأدمن
//                 'amount' => number_format($bill->amount_paid, 2) . ' ر.س',
//                 'date' => $bill->date,
//                 'status' => $bill->status == 'paid' ? 'مدفوعة' : 'غير مدفوعة',
//                 'raw_status' => $bill->status
//             ];
//         });

//         return response()->json([
//             'summary' => [
//                 'total_paid' => number_format($totalPaid, 2) . ' ر.س',
//                 'total_pending' => number_format($totalPending, 2) . ' ر.س',
//             ],
//             'invoices' => $formattedInvoices
//         ], 200);
//     }

//     /**
//      * 2. الحفظ (Store): الأدمن ينشئ فاتورة جديدة لجلسة معينة
//      */
//     public function store(Request $request)
//     {
//         $validated = $request->validate([
//             'clinic_session_id' => 'required|exists:clinic_sessions,id',
//             'amount_paid' => 'required|numeric|min:0',
//             'date' => 'required|date',
//             'status' => 'nullable|string|in:paid,unpaid'
//         ]);

//         $bill = Bill::create([
//             'clinic_session_id' => $validated['clinic_session_id'],
//             'amount_paid' => $validated['amount_paid'],
//             'date' => $validated['date'],
//             'status' => $validated['status'] ?? 'unpaid',
//         ]);

//         return response()->json([
//             'message' => 'تم إنشاء الفاتورة بنجاح عن طريق المسؤول',
//             'bill' => $bill
//         ], 201);
//     }

//     /**
//      * 3. التعديل (Update): الأدمن يراجع أو يعدل حالة فاتورة أو قيمتها
//      */
//     public function update(Request $request, $id)
//     {
//         $bill = Bill::find($id);

//         if (!$bill) {
//             return response()->json(['message' => 'الفاتورة غير موجودة'], 404);
//         }

//         $validated = $request->validate([
//             'clinic_session_id' => 'sometimes|required|exists:clinic_sessions,id',
//             'amount_paid' => 'sometimes|required|numeric|min:0',
//             'date' => 'sometimes|required|date',
//             'status' => 'sometimes|required|string|in:paid,unpaid'
//         ]);

//         $bill->update($validated);

//         return response()->json([
//             'message' => 'تم تحديث الفاتورة بنجاح',
//             'bill' => $bill
//         ], 200);
//     }
}