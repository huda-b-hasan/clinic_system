<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    /**
     * 1. جلب جميع المواد (سواء متوفرة أو نافدة)
     */
    public function index(): JsonResponse
    {
        $materials = Material::all();

        return response()->json([
            'success' => true,
            'data' => $materials,
        ], 200);
    }

    /**
     * 2. جلب جميع المواد المتاحة فقط (التي كميتها أكبر من 0)
     */
    public function getAvailableMaterials(): JsonResponse
    {
        $materials = Material::where('quantity', '>', 0)
            ->select('id', 'name', 'unit_price', 'quantity')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $materials,
        ], 200);
    }

    /**
     * 3. إضافة مادة جديدة للمخزن
     */
    public function store(Request $request)
    {
        // 1. التحقق من البيانات الأساسية للكتالوج فقط
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit_price' => 'required|numeric|min:0',
        ]);

        // 2. إنشاء كرت المادة بكمية صفرية
        $material = Material::create([
            'name' => $validated['name'],
            'quantity' => 0, 
            'unit_price' => $validated['unit_price'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعريف المادة  بنجاح.',
            'data' => $material,
        ], 201);
    }

    /**
     * 4. عرض تفاصيل مادة واحدة عبر ID
     */
    public function show($id): JsonResponse
    {
        $material = Material::find($id);

        if (! $material) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $material,
        ], 200);
    }

    /**
     * 5. تعديل بيانات مادة
     */
    public function update(Request $request, $id): JsonResponse
    {
        $material = Material::find($id);

        if (! $material) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'unit_price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
        ]);

        $material->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المادة بنجاح',
            'data' => $material,
        ], 200);
    }

    /**
     * 6. حذف مادة من المخزن
     */
    public function destroy($id): JsonResponse
    {
        $material = Material::find($id);

        if (! $material) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة',
            ], 404);
        }

        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المادة بنجاح',
        ], 200);
    }

    /**
     * 7. خصم كمية مستهلكة من المادة
     */
    public function deductQuantity(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'used_quantity' => 'required|integer|min:1',
        ]);

        $material = Material::find($id);

        if (! $material) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة',
            ], 404);
        }

        if ($material->quantity < $validated['used_quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'الكمية المطلوبة غير متوفرة بالمخزن. المتاح حالياً: '.$material->quantity,
            ], 400);
        }

        $material->quantity -= $validated['used_quantity'];
        $material->save();

        return response()->json([
            'success' => true,
            'message' => 'تم خصم الكمية بنجاح',
            'data' => $material,
        ], 200);
    }

    /**
     * 8. إعادة تزويد / زيادة كمية المادة
     */
    public function restock(Request $request, $id)
    {
        // 1. التحقق من صحة البيانات المدخلة
        $request->validate([
            'quantity_added' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'invoice_date' => 'nullable|date',
        ]);

        try {
            // 2. تنفيذ العملية داخل Transaction لضمان الأمان
            $result = DB::transaction(function () use ($request, $id) {
                $material = Material::findOrFail($id);

                $quantityAdded = $request->quantity_added;
                $unitPrice = $request->unit_price;
                $totalPrice = $quantityAdded * $unitPrice;
                $invoiceDate = $request->invoice_date ?? now()->toDateString();

                // أ) إنشاء فاتورة التوريد والشراء
                $invoice = $material->invoices()->create([
                    'quantity_added' => $quantityAdded,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'invoice_date' => $invoiceDate,
                ]);

                // ب) زيادة كمية المخزن الكلية وتحديث آخر سعر شراء للمادة
                $material->increment('quantity', $quantityAdded);
                $material->update(['unit_price' => $unitPrice]);

                return [
                    'material' => $material,
                    'invoice' => $invoice,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تمت إضافة الشحنة وتسجيل فاتورة المشتريات بنجاح',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ الفاتورة: '.$e->getMessage(),
            ], 500);
        }
    }
}
