<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromoCodeController extends Controller
{
    /**
     * عرض جميع أكواد الخصم مع الفلترة والبحث
     */
    public function index(Request $request)
    {
        $query = PromoCode::with('treatment');

        // 1. البحث باسم الكود
        if ($request->filled('search')) {
            $query->where('code', 'like', '%'.$request->search.'%');
        }

        // 2. الفلترة حسب نوع الخصم (percentage / fixed)
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
     * إحصائيات سريعة لكروت أعلى الصفحة
     */
    public function stats()
    {
        try {
            // 1. حساب الأكواد النشطة وغير المنتهية
            $activePromos = PromoCode::where('is_active', true)
                ->where('expiry_date', '>=', now()->toDateString())
                ->count();

            // 2. عدد المرضى المستفيدين من الكوبونات
            $beneficiariesCount = DB::table('promo_code_patient')->count();

            // 3. إجمالي عدد مرات الاستخدام المجمعة من جدول الأكواد
            $totalUsageCount = PromoCode::sum('used_count');

            return response()->json([
                'status' => true,
                'data' => [
                    'active_promos' => $activePromos,
                    'beneficiaries_count' => $beneficiariesCount,
                    'total_usage_count' => $totalUsageCount,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء كود خصم جديد
     */
    /**
     * إنشاء كود خصم جديد
     */
    public function store(Request $request)
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

        $validated['code'] = strtoupper($validated['code']);
        $promoCode = PromoCode::create($validated);

        return response()->json([
            'message' => 'تم إنشاء كود الخصم بنجاح ',
            'data' => $promoCode->load('treatment'),
        ], 201);
    }

    /**
     * تعديل كود خصم
     */
    public function update(Request $request, $id)
    {
        $promoCode = PromoCode::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code,'.$id,
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
            'message' => 'تم تعديل كود الخصم بنجاح',
            'data' => $promoCode->load('treatment'),
        ], 200);
    }

    /**
     * عرض تفاصيل كود معين
     */
    public function show($id)
    {
        $promoCode = PromoCode::with('treatment')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $promoCode,
        ], 200);
    }

    /**
     * حذف كود خصم
     */
    public function destroy($id)
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->delete();

        return response()->json([
            'message' => 'تم حذف كود الخصم بنجاح',
        ], 200);
    }
}
