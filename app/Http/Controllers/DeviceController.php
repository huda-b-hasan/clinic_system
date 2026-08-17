<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * عرض قائمة جميع الأجهزة الطبية
     */
    public function index()
    {
        $devices = Device::all();

        return response()->json([
            'status' => 'success',
            'data' => $devices,
        ], 200);
    }

    /**
     * إضافة جهاز جديد إلى النظام
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'last_maintenance' => 'nullable|date',
        ]);

        $device = Device::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تم إضافة الجهاز بنجاح',
            'data' => $device,
        ], 201);
    }

    /**
     * تحديث بيانات جهاز موجود
     */
    public function update(Request $request, $id)
    {
        $device = Device::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'model' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'last_maintenance' => 'nullable|date',
        ]);

        $device->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث بيانات الجهاز بنجاح',
            'data' => $device,
        ], 200);
    }

    /**
     * حذف جهاز من النظام
     */
    public function destroy($id)
    {
        $device = Device::findOrFail($id);
        $device->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف الجهاز بنجاح',
        ], 200);
    }
}