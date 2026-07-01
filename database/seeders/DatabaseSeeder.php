<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Treatment;
use App\Models\Device;
use App\Models\Material;
use App\Models\Appointment;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. تشغيل الـ Seeders الأساسية بالترتيب المنطقي الصحيح
        $this->call([
            RoleSeeder::class,        // الأدوار أولاً لكي يجدها جدول المستخدمين
            RoomSeeder::class,        // الغرف
            MaterialSeeder::class,    // المواد الطبية
            DeviceSeeder::class,      // الأجهزة
            TreatmentSeeder::class,   // العلاجات / الخدمات
            PatientSeeder::class,     // المرضى
            UserSeeder::class,        // المستخدمين (ويرتبط بالأدوار تلقائياً بداخل ملفكِ المعدل)
            AppointmentSeeder::class, // المواعيد (تعتمد على المريض، الطبيب، والغرفة)
            SessionSeeder::class,     // الجلسات (تعتمد على الموعد)
            BillSeeder::class,        // الفواتير (تعتمد على الجلسة)
            RatingSeeder::class,      // التقييمات (تعتمد على المستخدم والعلاج)
        ]);

        // 2. ربط جداول العلاقات (Pivot Tables) المتبقية تلقائياً
        $this->seedPivotTables();
    }

    /**
     * دالة مخصصة لربط جداول المتعدد إلى متعدد (Pivot Tables)
     */
    protected function seedPivotTables(): void
    {
        // جلب أول سجل من كل جدول لعمل عملية الربط التجريبية
        $treatment = Treatment::first();
        $device = Device::first();
        $material = Material::first();
        $appointment = Appointment::first();

        // أ. ربط العلاج بالجهاز في جدول treatment_device
        if ($treatment && $device) {
            DB::table('device_treatment')->insertOrIgnore([
                'treatment_id' => $treatment->treatment_id ?? $treatment->id,
                'device_id' => $device->device_id ?? $device->id,
            ]);
        }

        // ب. ربط العلاج بالمادة الطبية في جدول treatment_material
        if ($treatment && $material) {
            DB::table('material_treatment')->insertOrIgnore([
                'treatment_id' => $treatment->treatment_id ?? $treatment->id,
                'material_id' => $material->material_id ?? $material->id,
            ]);
        }

        // جـ. ربط العلاج بالموعد في جدول treatment_appointment
        if ($treatment && $appointment) {
            DB::table('appointment_treatment')->insertOrIgnore([
                'treatment_id' => $treatment->treatment_id ?? $treatment->id,
                'appointment_id' => $appointment->appointment_id ?? $appointment->id,
            ]);
        }
    }
}