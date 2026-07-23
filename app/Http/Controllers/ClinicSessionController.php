<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\ClinicSessions;
use App\Models\Material;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicSessionController extends Controller
{
    /**
     * جلب تفاصيل الجلسة والموعد بناءً على appointment_id
     */
    public function getSessionDetails($appointmentId): JsonResponse
    {
        // 1. جلب الموعد مع العلاقات المطلوبة
        $appointment = Appointment::with([
            'patient',
            'room',
            'treatments.devices',
            'treatments.materials', // المواد الأساسية المربوطة بالعلاج
        ])->find($appointmentId);

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'الموعد غير موجود',
            ], 404);
        }

        // 2. حساب عمر المريض إن وجد تاريخ الميلاد
        $patientAge = null;
        if ($appointment->patient && $appointment->patient->birthdate) {
            $patientAge = Carbon::parse($appointment->patient->birthdate)->age;
        }

        // 3. تجهيز البيانات وتنظيمها
        return response()->json([
            'success' => true,
            'data' => [
                'appointment_id' => $appointment->id,
                'appointment_date' => $appointment->appointment_date,
                'patient' => [
                    'id' => $appointment->patient->id ?? null,
                    'name' => $appointment->patient->name ?? 'غير محدد',
                    'phone' => $appointment->patient->phone ?? 'غير محدد',
                    'age' => $patientAge,
                ],
                'room' => [
                    'id' => $appointment->room->id ?? null,
                    'name' => $appointment->room->name ?? 'غير محددة',
                ],
                'treatments' => $appointment->treatments->map(function ($treatment) {
                    return [
                        'id' => $treatment->id,
                        'name' => $treatment->name,
                        'base_price' => $treatment->base_price,
                        'booked_price' => $treatment->pivot->booked_price ?? $treatment->base_price,
                        'devices' => $treatment->devices->pluck('name'),
                        'default_materials' => $treatment->materials->map(function ($mat) {
                            return [
                                'id' => $mat->id,
                                'name' => $mat->name,
                                'unit_price' => $mat->unit_price,
                            ];
                        }),
                    ];
                }),
            ],
        ], 200);
    }

    /**
     * إنهاء الجلسة، خصم المواد من المخزن، وإصدار الفاتورة
     */
    public function completeSession(Request $request, $appointmentId): JsonResponse
    {
        // 1. التحقق من البيانات المرسلة
        $request->validate([
            'doctor_notes' => 'nullable|string',
            'materials' => 'nullable|array', // [material_id => quantity]
            'materials.*' => 'integer|min:0',
        ]);

        // 2. جلب الموعد وتفاصيل العلاج المحجوز
        $appointment = Appointment::with(['treatments', 'patient'])->find($appointmentId);

        if (! $appointment) {
            return response()->json(['success' => false, 'message' => 'الموعد غير موجود'], 404);
        }

        if ($appointment->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'هذه الجلسة تم إغلاقها مسبقاً'], 400);
        }

        DB::beginTransaction();
        try {
            // أ. إنشاء سجل الجلسة الطبية
            $session = ClinicSessions::create([
                'appointment_id' => $appointment->id,
                'doctor_notes' => $request->input('doctor_notes'),
            ]);

            // ب. حساب سعر العلاجات (المحسوبة مسبقاً وسعرها صافي في booked_price)
            $treatmentsTotal = $appointment->treatments->sum(function ($treatment) {
                return $treatment->pivot->booked_price ?? $treatment->base_price;
            });

            // ج. خصم المواد المستهلكة وحساب مجموع تكلفتها
            $materialsTotal = 0;
            $usedMaterials = $request->input('materials', []);

            foreach ($usedMaterials as $materialId => $qty) {
                if ($qty > 0) {
                    $material = Material::find($materialId);
                    if ($material) {
                        // التأكد من توفر الكمية الكافية بالمخزن
                        if ($material->quantity < $qty) {
                            DB::rollBack();

                            return response()->json([
                                'success' => false,
                                'message' => "الكمية المتاحة من ({$material->name}) غير كافية بالمخزن",
                            ], 400);
                        }

                        // خصم المستهلك من المخزن
                        $material->decrement('quantity', $qty);

                        // إضافة تكلفة المادة للفاتورة
                        $materialsTotal += ($material->unit_price * $qty);
                        // ربط المادة بالجلسة وتخزين الكمية والتعريفة
                        $session->materials()->attach($materialId, [
                            'quantity' => $qty,
                            'unit_price' => $material->unit_price,
                        ]);
                    }
                }

            }

            // د. المبلغ الإجمالي النهائي للفاتورة
            $grandTotal = $treatmentsTotal + $materialsTotal;

            // هـ. إصدار الفاتورة
            $bill = Bill::create([
                'clinic_session_id' => $session->id,
                'amount_paid' => $grandTotal,
                'date' => Carbon::today()->toDateString(),
                'status' => 'unpaid',
            ]);

            // و. تحديث حالة الموعد إلى مكتمل (completed)
            $appointment->update(['status' => 'completed']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنهاء الجلسة وإصدار الفاتورة بنجاح',
                'data' => [
                    'session_id' => $session->id,
                    'bill_id' => $bill->id,
                    'total_amount' => $grandTotal,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنهاء الجلسة: '.$e->getMessage(),
            ], 500);
        }
    }
}
