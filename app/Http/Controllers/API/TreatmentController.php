<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'image' => 'nullable|string',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $treatment = Treatment::create($request->all());

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
            'base_price' => 'sometimes|required|numeric',
            'category' => 'sometimes|required|string',
            'duration' => 'sometimes|required|integer',
            'features' => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $treatment->update($request->all());

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

        $treatment->delete();

        return response()->json(['message' => 'تم حذف الخدمة بنجاح'], 200);
    }
}
