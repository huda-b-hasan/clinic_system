<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Material;
use App\Models\Rating;
use App\Models\MaterialInvoice;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function getDashboardData()
    {
        try {
            // الحصول على السنة والشهر الحاليين
            $currentYear  = Carbon::now()->year;
            $currentMonth = Carbon::now()->month;

            // 1. حساب إجمالي الإيرادات للشهر الحالي
            $totalRevenue = (float) Bill::whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->where('status', 'paid')
                ->sum('amount_paid');

            // 2. حساب إجمالي المصروفات للشهر الحالي
            $totalExpenses = (float) MaterialInvoice::whereYear('invoice_date', $currentYear)
                ->whereMonth('invoice_date', $currentMonth)
                ->sum('total_price');

            // 3. حساب صافي الأرباح (الإيرادات - المصروفات)
            $netProfit = $totalRevenue - $totalExpenses;

            // تنسيق المبلغ للعرض (سواء كان موجباً أم سالباً)
            // في حال القيمة سالبة ستظهر مثل: -500,000 ل.س
            $formattedRevenue = number_format($netProfit, 0) . ' ل.س';

            // 4. إجمالي عدد المرضى
            $totalPatients = Patient::count();

            // 5. متوسط التقييم العام
            $overallRating = Rating::avg('stars_number');
            $averageRatingFormatted = $overallRating ? round($overallRating, 1) : 0;

            // 6. المواد المنخفضة في المخزن
            $lowStockMaterials = Material::where('quantity', '<=', 10)->get([
                'id',
                'name',
                'quantity'
            ]);

            // 7. الخدمات الأكثر طلباً
            $topServices = Treatment::withCount('appointments')
                ->withAvg('ratings', 'stars_number')
                ->orderBy('appointments_count', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => [
                        'total_revenue'   => $formattedRevenue, // يظهر السالب بشكل صريح إن وجد
                        'raw_revenue'     => $netProfit,        // القيمة الرقمية الخام (موجبة أو سالبة)
                        
                        'gross_revenue'   => $totalRevenue,     // إجمالي المداخيل
                        'total_expenses'  => $totalExpenses,    // إجمالي المصاريف
                        
                        'total_patients'  => $totalPatients,
                        'average_rating'  => $averageRatingFormatted,
                        'low_stock_count' => $lowStockMaterials->count(),
                    ],
                    'low_stock_materials' => $lowStockMaterials,
                    'top_services'        => $topServices,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء جلب بيانات لوحة التحكم: ' . $e->getMessage()
            ], 500);
        }
    }
}