<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoCodeController extends Controller
{
    /**
     * عرض جميع أكواد الخصم مع إمكانية البحث والفلترة حسب النوع أو الحالة
     */
    public function index(Request $request): JsonResponse
    {
        $query = PromoCode::with('treatment');

        // 1. البحث برمز الكود
        if ($request->filled('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        // 2. الفلترة حسب نوع الخصم (نسبة مئوية / مبلغ ثابت)
        if ($request->filled('type')) {
            $query->where('discount_type', $request->type);
        }

        // 3. الفلترة حسب الحالة (نشط / منتهي)
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true)
                    ->where('expiry_date', '>=', Carbon::today());
            } elseif ($request->status === 'expired') {
                $query->where(function ($q) {
                    $q->where('expiry_date', '<', Carbon::today())
                        ->orWhere('is_active', false);
                });
            }
        }

        $promoCodes = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $promoCodes,
        ], 200);
    }

    /**
     * جلب إحصائيات سريعة لعرضها في البطاقات العلوية لوحة التحكم
     */
    public function stats(): JsonResponse
    {
        try {
            // 1. حساب الأكواد النشطة والتي لم تنتهي صلاحيتها
            $activePromos = PromoCode::where('is_active', true)
                ->where('expiry_date', '>=', now()->toDateString())
                ->count();

            // 2. عدد المرضى المستفيدين الفريدين من الكوبونات
            $beneficiariesCount = DB::table('promo_code_patient')->count();

            // 3. إجمالي عدد مرات الاستخدام المجمعة للأكواد
            $totalUsageCount = PromoCode::sum('used_count');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'active_promos' => $activePromos,
                    'beneficiaries_count' => $beneficiariesCount,
                    'total_usage_count' => $totalUsageCount,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء كود خصم جديد في النظام
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:promo_codes,code|max:50',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'treatment_id' => 'nullable|exists:treatments,id',
            'expiry_date' => 'required|date',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        // تحويل الحروف إلى أحرف كبيرة (Uppercase) لضمان التوحيد
        $validated['code'] = strtoupper($validated['code']);
        $promoCode = PromoCode::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تم إنشاء كود الخصم بنجاح',
            'data' => $promoCode->load('treatment'),
        ], 201);
    }

    /**
     * عرض تفاصيل كود خصم محدد بواسطة المعرف (ID)
     */
    public function show($id): JsonResponse
    {
        $promoCode = PromoCode::with('treatment')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $promoCode,
        ], 200);
    }

    /**
     * تعديل وتحديث بيانات كود خصم موجود
     */
    public function update(Request $request, $id): JsonResponse
    {
        $promoCode = PromoCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,' . $id,
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'treatment_id' => 'nullable|exists:treatments,id',
            'expiry_date' => 'required|date',
            'usage_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $promoCode->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تعديل كود الخصم بنجاح',
            'data' => $promoCode->load('treatment'),
        ], 200);
    }

    /**
     * حذف كود خصم من النظام نهائياً
     */
    public function destroy($id): JsonResponse
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف كود الخصم بنجاح',
        ], 200);
    }
}