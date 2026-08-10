<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TreatmentController extends Controller
{
    public function index()
    {
        $treatments = Treatment::all();
        return response()->json($treatments, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'category' => 'required|string',
            'duration' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // قبول ملف صورة
            'features' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except('image');

        // معالجة رفع ملف الصورة وحفظ مسارها النصي
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('treatments', 'public');
            $data['image'] = $imagePath;
        }

        $treatment = Treatment::create($data);

        return response()->json([
            'message' => 'تم إضافة الخدمة بنجاح',
            'data' => $treatment
        ], 201);
    }

    public function show($id)
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json(['message' => 'الخدمة غير موجودة'], 404);
        }

        return response()->json($treatment, 200);
    }

    public function update(Request $request, $id)
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json(['message' => 'الخدمة غير موجودة لتعديلها'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'sometimes|required|numeric',
            'discount_price' => 'nullable|numeric',
            'category' => 'sometimes|required|string',
            'duration' => 'sometimes|required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'features' => 'nullable|array',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except('image');

        // إذا تم إرسال صورة جديدة
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة إذا كانت موجودة
            if ($treatment->image && Storage::disk('public')->exists($treatment->image)) {
                Storage::disk('public')->delete($treatment->image);
            }

            // حفظ الصورة الجديدة
            $imagePath = $request->file('image')->store('treatments', 'public');
            $data['image'] = $imagePath;
        }

        $treatment->update($data);

        return response()->json([
            'message' => 'تم تحديث الخدمة بنجاح',
            'data' => $treatment
        ], 200);
    }

    public function destroy($id)
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json(['message' => 'الخدمة غير موجودة لحذفها'], 404);
        }

        // حذف الصورة المرتبطة بالخدمة من السيرفر عند الحذف
        if ($treatment->image && Storage::disk('public')->exists($treatment->image)) {
            Storage::disk('public')->delete($treatment->image);
        }

        $treatment->delete();

        return response()->json(['message' => 'تم حذف الخدمة بنجاح'], 200);
    }

    public function toggleStatus($id)
    {
        $treatment = Treatment::find($id);

        if (!$treatment) {
            return response()->json(['message' => 'الخدمة غير موجودة'], 404);
        }

        $treatment->status = ($treatment->status === 'active') ? 'inactive' : 'active';
        $treatment->save();

        return response()->json([
            'message' => 'تم تغيير حالة الخدمة بنجاح',
            'status' => $treatment->status
        ], 200);
    }
}