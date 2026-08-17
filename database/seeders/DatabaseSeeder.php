<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,                // 1. الأدوار
            UserSeeder::class,                // 2. المستخدمون
            PatientSeeder::class,             // 3. المرضى
            DeviceSeeder::class,              // 4. الأجهزة الطبية
            MaterialSeeder::class,            // 5. المواد الطبية
            TreatmentSeeder::class,           // 6. الخدمات
            DeviceTreatmentSeeder::class,     // 7. ربط الأجهزة بالخدمات
            MaterialTreatmentSeeder::class,   // 8. ربط المواد بالخدمات
            PromoCodeSeeder::class,           // 9. أكواد الخصم
            PromoCodePatientSeeder::class,    // 10. استخدام المرضى لأكواد الخصم
            RoomSeeder::class,                // 11. الغرف
            AppointmentSeeder::class,         // 12. المواعيد
            RatingSeeder::class,              // 13. التقييمات
            SessionSeeder::class,             // 14. الجلسات والفواتير
            ClinicSessionMaterialSeeder::class, // 15. المواد المستهلكة في الجلسات
        ]);
    }
}