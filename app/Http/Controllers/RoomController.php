<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

namespace App\Http\Controllers;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * 1. شاشة الريسبشن: جلب إحصائيات الغرف والمواعيد النشطة حالياً
     */
    public function getReceptionDashboard()
    {
        // جلب كل الغرف مع جلب الموعد أو الجلسة الشغالة اليوم
        $rooms = Room::with(['appointments' => function ($query) {
            $query->whereIn('status', ['in_progress', 'confirmed', 'pending'])
                  ->whereDate('appointment_date', Carbon::today())
                  ->with(['patient', 'doctor', 'treatments']);
        }])->get();

        // تجهيز التفاصيل لكل غرفة
        $roomsDetailed = $rooms->map(function ($room) {
            $currentAppointment = $room->appointments->first();

            return [
                'id'          => $room->id,
                'name'        => $room->name,
                'type'        => $room->type,
                'status'      => $room->status,
                'is_occupied' => $room->status === 'busy' || $currentAppointment !== null,
                
                'current_session' => $currentAppointment ? [
                    'appointment_id'   => $currentAppointment->id,
                    'appointment_date' => $currentAppointment->appointment_date,
                    'status'           => $currentAppointment->status,
                    'patient' => [
                        'id'    => $currentAppointment->patient->id ?? null,
                        'name'  => $currentAppointment->patient->name ?? 'غير محدد',
                        'phone' => $currentAppointment->patient->phone ?? null,
                    ],
                    'doctor' => [
                        'id'   => $currentAppointment->doctor->id ?? null,
                        'name' => $currentAppointment->doctor->name ?? 'غير محدد',
                    ],
                    'treatments' => $currentAppointment->treatments->map(fn($t) => [
                        'id'   => $t->id,
                        'name' => $t->name,
                    ]),
                ] : null,
            ];
        });

        // الإحصائيات العامة
        $totalRooms     = $rooms->count();
        $occupiedCount  = $roomsDetailed->where('is_occupied', true)->count();
        $availableCount = $totalRooms - $occupiedCount;

        return response()->json([
            'status' => 'success',
            'summary' => [
                'total_rooms'     => $totalRooms,
                'occupied_rooms'  => $occupiedCount,
                'available_rooms' => $availableCount,
                'occupancy_rate'  => $totalRooms > 0 ? round(($occupiedCount / $totalRooms) * 100, 1) . '%' : '0%',
            ],
            'rooms' => $roomsDetailed,
        ], 200);
    }

    /**
     * 2. عرض قائمة بكافة الغرف مع إمكانية الفلترة (مثلاً حسب الحالة)
     */
    public function index()
    {
        $rooms = Room::all();
        return response()->json(['status' => 'success', 'data' => $rooms], 200);
    }

    /**
     * 3. إضافة غرفة جديدة للعيادة
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'nullable|string', // default: available
            'type'   => 'nullable|string',
        ]);

        $room = Room::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم إضافة الغرفة بنجاح',
            'data'    => $room,
        ], 210);
    }


    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $room = Room::findOrFail($id);
        $room->update(['status' => $validated['status']]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث حالة الغرفة بنجاح',
            'data'    => $room,
        ], 200);
    }
    /**
     * تحديث بيانات الغرفة
     */
    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'status' => 'nullable|string',
            'type'   => 'nullable|string',
        ]);

        $room->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث بيانات الغرفة بنجاح',
            'data'    => $room,
        ], 200);
    }

    /**
     * حذف غرفة
     */
    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'تم حذف الغرفة بنجاح'
        ], 200);
    }
}
