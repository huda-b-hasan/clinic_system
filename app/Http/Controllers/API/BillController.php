<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function getBillDataPatient(Request $request)
    {
        $userId = session('user_id');

        $patient = \DB::table('patients')->where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
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

            if (! empty($treatmentIds)) {
                $treatments = \DB::table('treatments')
                    ->whereIn('id', $treatmentIds)
                    ->get();

                $matchedTreatment = $treatments->first(function ($treatment) use ($bill) {
                    return (float) $treatment->discount_price == (float) $bill->amount_paid ||
                           (float) $treatment->base_price == (float) $bill->amount_paid;
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
                'invoice_number' => 'B-'.str_pad($bill->id, 4, '0', STR_PAD_LEFT).'#',
                'session_name' => $sessionName,
                'amount' => number_format($bill->amount_paid, 2).' ل.س',
                'date' => $bill->date,
                'status' => $bill->status == 'paid' ? 'مدفوعة' : 'غير مدفوعة',
                'raw_status' => $bill->status,
            ];
        });

        return response()->json([
            'summary' => [
                'total_paid' => number_format($totalPaid, 2).' ل.س',
                'total_pending' => number_format($totalPending, 2).' ل.س',
            ],
            'invoices' => $formattedInvoices,
        ], 200);
    }

    public function getPendingBillsCount(Request $request)
    {
        $userId = session('user_id');

        // 1. جلب سجل المريض المرتبط بالحساب الحالي
        $patient = \DB::table('patients')->where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
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
            'pending_bills_count' => $pendingCount,
        ], 200);
    }

    public function getBillsSummary(Request $request)
    {
        try {
            // 1. حساب الأعداد بناءً على الحقول الصحيحة في جدولكِ
            $paidBillsCount = Bill::where('status', 'paid')->count();
            $unpaidBillsCount = Bill::where('status', 'unpaid')->count();

            // 2. جلب الفواتير مع علاقة الجلسة والمريض إن وجدت لتفادي البطء
            // (تأكدي من تعريف علاقة clinicSession داخل موديل Bill إذا أردتِ جلب اسم المريض ديناميكياً)
            $allBills = Bill::orderBy('created_at', 'desc')->get();

            $paidBillsList = [];
            $unpaidBillsList = [];

            foreach ($allBills as $bill) {

                // محاولة الوصول لاسم المريض من خلال الجلسة المشتركة بأمان
                $patientName = 'مريض غير معروف';

                if (method_exists($bill, 'clinicSession') && $bill->clinicSession) {
                    $session = $bill->clinicSession;
                    // إذا كانت الجلسة مرتبطة بال مريض مباشرة أو عبر موعد (appointment)
                    if ($session->patient) {
                        $patientName = $session->patient->name;
                    } elseif ($session->appointment && $session->appointment->patient) {
                        $patientName = $session->appointment->patient->name;
                    }
                } else {
                    // اسم احتياطي يحمل رقم الجلسة لكي لا يظهر الاسم فارغاً
                    $patientName = 'جلسة عيادة رقم (#'.$bill->clinic_session_id.')';
                }

                // تجهيز البيانات بالأسماء المطابقة لملف الـ Migration الخاص بكِ
                $billData = [
                    'id' => $bill->id,
                    'bill_number' => '#'.$bill->id,
                    'patient_name' => $patientName,
                    'amount' => $bill->amount_paid, // الحقل الصحيح من الـ Migration
                    'status' => $bill->status,      // الحقل الصحيح من الـ Migration
                    'date' => $bill->date,          // حقل التاريخ من الـ Migration
                ];

                // تقسيم الفواتير حسب الحالة الحقيقية لها
                if ($bill->status === 'paid') {
                    $paidBillsList[] = $billData;
                } else {
                    $unpaidBillsList[] = $billData;
                }
            }

            // 3. إرجاع البيانات بنجاح للفرونت إند
            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => [
                        'paid_count' => $paidBillsCount,
                        'unpaid_count' => $unpaidBillsCount,
                    ],
                    'bills' => [
                        'paid' => $paidBillsList,
                        'unpaid' => $unpaidBillsList,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ في السيرفر: '.$e->getMessage(),
            ], 500);
        }
    }

    public function pay(Request $request, $id)
    {
        $bill = Bill::find($id);

        if (! $bill) {
            return response()->json([
                'status' => 'error',
                'message' => 'الفاتورة غير موجودة!',
            ], 404);
        }

        $bill->status = 'paid';
        $bill->date = now()->toDateString(); 
        $bill->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تمت تسوية الفاتورة بنجاح.',
            'data' => $bill,
        ], 200);
    }
}
