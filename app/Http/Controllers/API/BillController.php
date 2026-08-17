<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;

class BillController extends Controller
{
    /**
     * جلب بيانات الفواتير الخاصة بالمريض الحالي مع ملخص المبالغ المدفوعة والمعلقة
     */
    public function getBillDataPatient(Request $request)
    {
        $userId = session('user_id');

        // البحث عن ملف المريض المرتبط بحساب المستخدم الحالي
        $patient = \DB::table('patients')->where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
            ], 404);
        }

        // جلب الفواتير المرتبطة بمواعيد المريض عبر الجلسات العلاجية
        $bills = \DB::table('bills')
            ->join('clinic_sessions', 'bills.clinic_session_id', '=', 'clinic_sessions.id')
            ->join('appointments', 'clinic_sessions.appointment_id', '=', 'appointments.id')
            ->where('appointments.patient_id', $patient->id)
            ->select('bills.*', 'clinic_sessions.appointment_id')
            ->get();

        // حساب إجمالي الأموال المدفوعة والمعلقة
        $totalPaid = $bills->where('status', 'paid')->sum('amount_paid');
        $totalPending = $bills->whereIn('status', ['unpaid', 'partially_paid'])->sum('amount_paid');

        // تنسيق الفواتير لعرضها بشكل منظم في الواجهة الأمامية
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
                'amount' => number_format($bill->amount_paid, 0).' ل.س',
                'date' => $bill->date,
                'status' => $bill->status == 'paid' ? 'مدفوعة' : 'غير مدفوعة',
                'raw_status' => $bill->status,
            ];
        });

        return response()->json([
            'summary' => [
                'total_paid' => number_format($totalPaid, 0).' ل.س',
                'total_pending' => number_format($totalPending, 0).' ل.س',
            ],
            'invoices' => $formattedInvoices,
        ], 200);
    }

    /**
     * جلب عداد الفواتير المعلقة (غير المدفوعة) للمريض الحالي
     */
    public function getPendingBillsCount(Request $request)
    {
        $userId = session('user_id');

        $patient = \DB::table('patients')->where('user_id', $userId)->first();

        if (! $patient) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.',
            ], 404);
        }

        $pendingCount = \DB::table('bills')
            ->join('clinic_sessions', 'bills.clinic_session_id', '=', 'clinic_sessions.id')
            ->join('appointments', 'clinic_sessions.appointment_id', '=', 'appointments.id')
            ->where('appointments.patient_id', $patient->id)
            ->whereIn('bills.status', ['unpaid', 'partially_paid'])
            ->count(); 

        return response()->json([
            'status' => true,
            'pending_bills_count' => $pendingCount,
        ], 200);
    }

    /**
     * جلب ملخص فواتير الآدمن مقسمة (مدفوعة / غير مدفوعة) مع الإحصائيات
     */
    public function getBillsSummary(Request $request)
    {
        try {
            $paidBillsCount = Bill::where('status', 'paid')->count();
            $unpaidBillsCount = Bill::where('status', 'unpaid')->count();

            $allBills = Bill::orderBy('created_at', 'desc')->get();

            $paidBillsList = [];
            $unpaidBillsList = [];

            foreach ($allBills as $bill) {
                // جلب اسم المريض بأمان من الجلسة أو الموعد المرتبط
                $patientName = 'مريض غير معروف';

                if (method_exists($bill, 'clinicSession') && $bill->clinicSession) {
                    $session = $bill->clinicSession;
                    if ($session->patient) {
                        $patientName = $session->patient->name;
                    } elseif ($session->appointment && $session->appointment->patient) {
                        $patientName = $session->appointment->patient->name;
                    }
                } else {
                    $patientName = 'جلسة عيادة رقم (#'.$bill->clinic_session_id.')';
                }

                $billData = [
                    'id' => $bill->id,
                    'bill_number' => '#'.$bill->id,
                    'patient_name' => $patientName,
                    'amount' => $bill->amount_paid, 
                    'status' => $bill->status,     
                    'date' => $bill->date,          
                ];

                // تصنيف الفواتير بناءً على حالتها
                if ($bill->status === 'paid') {
                    $paidBillsList[] = $billData;
                } else {
                    $unpaidBillsList[] = $billData;
                }
            }

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

    /**
     * تسوية فواتير المريض وتغيير حالتها إلى مدفوعة (Paid)
     */
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

    /**
     * جلب البطاقات المالية للإيرادات والمصروفات والأرباح مع سجلات الفواتير والمشتريات الشاملة
     */
    public function getFinancialCardsAndBills(Request $request)
    {
        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $today = now()->toDateString();

            // 1. حساب الإيرادات (السنوية، الشهرية، اليومية) للفواتير المدفوعة
            $yearlyRevenue = (float) Bill::whereYear('date', $currentYear)
                ->where('status', 'paid')
                ->sum('amount_paid');

            $monthlyRevenue = (float) Bill::whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->where('status', 'paid')
                ->sum('amount_paid');

            $dailyRevenue = (float) Bill::whereDate('date', $today)
                ->where('status', 'paid')
                ->sum('amount_paid');

            // 2. حساب المصروفات من جدول فواتير المواد
            $yearlyExpenses = 0;
            $monthlyExpenses = 0;
            if (\Schema::hasTable('material_invoices')) {
                $yearlyExpenses = (float) \DB::table('material_invoices')->whereYear('invoice_date', $currentYear)->sum('total_price');
                $monthlyExpenses = (float) \DB::table('material_invoices')->whereYear('invoice_date', $currentYear)->whereMonth('invoice_date', $currentMonth)->sum('total_price');
            }

            $yearlyNetProfit = $yearlyRevenue - $yearlyExpenses;
            $monthlyNetProfit = $monthlyRevenue - $monthlyExpenses;

            $totalUnpaid = (float) Bill::whereIn('status', ['unpaid', 'partially_paid'])
                ->sum('amount_paid');

            // 3. جلب فواتير المشتريات (المواد) إن وجدت وتنسيقها
            $purchaseInvoices = collect();
            if (\Schema::hasTable('material_invoices') && \Schema::hasTable('materials')) {
                $purchaseInvoices = \DB::table('material_invoices')
                    ->join('materials', 'material_invoices.material_id', '=', 'materials.id')
                    ->select('material_invoices.id', 'material_invoices.total_price', 'material_invoices.invoice_date', 'material_invoices.quantity_added', 'materials.name as material_name')
                    ->orderBy('material_invoices.invoice_date', 'desc')
                    ->get()
                    ->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'bill_number' => 'PUR-'.str_pad($invoice->id, 4, '0', STR_PAD_LEFT),
                            'patient_name' => $invoice->material_name,
                            'session_name' => $invoice->quantity_added,
                            'amount' => number_format($invoice->total_price, 0).' ل.س',
                            'date' => $invoice->invoice_date,
                            'status_text' => 'مشتريات',
                            'data_status' => 'expense',
                        ];
                    });
            }

            // 4. جلب فواتير المرضى وتنسيقها
            $patientBills = Bill::orderBy('created_at', 'desc')
                ->get()
                ->map(function ($bill) {
                    $patientName = 'مريض غير معروف';

                    try {
                        if ($bill->clinicSession) {
                            if ($bill->clinicSession->patient) {
                                $patientName = $bill->clinicSession->patient->name;
                            } elseif ($bill->clinicSession->appointment && $bill->clinicSession->appointment->patient) {
                                $patientName = $bill->clinicSession->appointment->patient->name;
                            }
                        }
                    } catch (\Exception $ex) {
                        // تجاهل أي خطأ في العلاقات لضمان عدم توقف السيرفر
                    }

                    $statusText = 'مدفوعة';
                    $dataStatus = 'paid';

                    if ($bill->status === 'unpaid') {
                        $statusText = 'غير مدفوعة';
                        $dataStatus = 'unpaid';
                    } elseif ($bill->status === 'partially_paid') {
                        $statusText = 'مستحقة';
                        $dataStatus = 'pending';
                    }

                    return [
                        'id' => $bill->id,
                        'bill_number' => '#'.str_pad($bill->id, 4, '0', STR_PAD_LEFT),
                        'patient_name' => $patientName,
                        'session_name' => '- ',
                        'amount' => number_format($bill->amount_paid, 0).' ل.س',
                        'date' => $bill->date,
                        'status_text' => $statusText,
                        'data_status' => $dataStatus,
                    ];
                });

            // دمج سجلات فواتير المرضى مع فواتير المشتريات في قائمة مالية واحدة متكاملة
            $allFinancialRecords = $patientBills->concat($purchaseInvoices);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'cards' => [
                        'yearly_revenue' => number_format($yearlyNetProfit, 0).' ل.س',
                        'monthly_revenue' => number_format($monthlyNetProfit, 0).' ل.س',
                        'daily_revenue' => number_format($dailyRevenue, 0).' ل.س',
                        'unpaid_total' => number_format($totalUnpaid, 0).' ل.س',
                    ],
                    'bills' => $allFinancialRecords,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطأ في السيرفر: '.$e->getMessage(),
            ], 500);
        }
    }
}