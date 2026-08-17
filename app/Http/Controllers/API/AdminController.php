<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Material;
use App\Models\MaterialInvoice;
use App\Models\Patient;
use App\Models\Rating;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * جلب بيانات لوحة التحكم الخاصة بالادمن
     */
    public function getDashboardData()
    {
        try {
            // الحصول على السنة والشهر الحاليين
            $currentYear = Carbon::now()->year;
            $currentMonth = Carbon::now()->month;

            // 1. حساب إجمالي الإيرادات للشهر الحالي (للفواتير المدفوعة فقط)
            $totalRevenue = (float) Bill::whereYear('date', $currentYear)
                ->whereMonth('date', $currentMonth)
                ->where('status', 'paid')
                ->sum('amount_paid');

            // 2. حساب إجمالي المصروفات للشهر الحالي (من فواتير المواد)
            $totalExpenses = (float) MaterialInvoice::whereYear('invoice_date', $currentYear)
                ->whereMonth('invoice_date', $currentMonth)
                ->sum('total_price');

            // 3. حساب صافي الأرباح (الإيرادات - المصروفات)
            $netProfit = $totalRevenue - $totalExpenses;

            // تنسيق المبلغ للعرض (سواء كان موجباً أم سالباً، مثل: -500,000 ل.س)
            $formattedRevenue = number_format($netProfit, 0).' ل.س';

            // 4. إجمالي عدد المرضى المسجلين في النظام
            $totalPatients = Patient::count();

            // 5. متوسط التقييم العام بناءً على عدد النجوم
            $overallRating = Rating::avg('stars_number');
            $averageRatingFormatted = $overallRating ? round($overallRating, 1) : 0;

            // 6. المواد المنخفضة في المخزن (الكمية أقل أو تساوي 10)
            $lowStockMaterials = Material::where('quantity', '<=', 5)->get([
                'id',
                'name',
                'quantity',
            ]);

            // 7. الخدمات الأكثر طلباً (مرتبة تنازلياً حسب عدد المواعيد مع متوسط التقييم)
            $topServices = Treatment::withCount('appointments')
                ->withAvg('ratings', 'stars_number')
                ->orderBy('appointments_count', 'desc')
                ->take(5)
                ->get();

            // إرجاع الاستجابة بنجاح مع البيانات المنسقة
            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => [
                        'total_revenue' => $formattedRevenue, // صافي الأرباح منسقاً (يظهر السالب بشكل صريح إن وجد)
                        'raw_revenue' => $netProfit,          // القيمة الرقمية الخام لصافي الأرباح (موجبة أو سالبة)

                        'gross_revenue' => $totalRevenue,     // إجمالي المداخيل (الإيرادات قبل خصم المصاريف)
                        'total_expenses' => $totalExpenses,   // إجمالي المصاريف

                        'total_patients' => $totalPatients,
                        'average_rating' => $averageRatingFormatted,
                        'low_stock_count' => $lowStockMaterials->count(),
                    ],
                    'low_stock_materials' => $lowStockMaterials,
                    'top_services' => $topServices,
                ],
            ], 200);

        } catch (\Exception $e) {
            // التعامل مع الأخطاء وإرجاع رسالة خطأ مناسبة
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء جلب بيانات لوحة التحكم: '.$e->getMessage(),
            ], 500);
        }
    }


    /**
     * جلب بيانات الملف الشخصي للادمن
     */
    public function getAdminProfile()
    {
        // 1. جلب معرف المستخدم من الجلسة الحالية
        $adminId = session('user_id');

        // التأكد من وجود جلسة نشطة للمستخدم
        if (! $adminId) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح: لم يتم تسجيل الدخول',
            ], 401);
        }

        // 2. البحث عن المستخدم في قاعدة البيانات بواسطة معرفه
        $admin = User::find($adminId);

        // التأكد من وجود المستخدم في قاعدة البيانات فعلياً
        if (! $admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'المستخدم غير موجود',
            ], 404);
        }

        // 3. إرجاع البيانات المطلوبة للملف الشخصي (مع الاسم والصور/البيانات الأساسية)
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,    // اسم المسؤول
                'email' => $admin->email,  // البريد الإلكتروني
                'role' => $admin->role ?? 'admin', // الدور (افتراضياً admin إن لم يوجد)
            ],
        ], 200);
    }
}