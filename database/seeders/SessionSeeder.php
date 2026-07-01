<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SessionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب سجلات المرضى كاملة
        $patients = DB::table('patients')->select('id', 'user_id')->get()->toArray();
        
        // 2. جلب معرف صلاحية الدكتور والدكتور
        $doctorRoleId = DB::table('roles')->where('name', 'doctor')->value('id');
        $doctorId = DB::table('role_user')->where('role_id', $doctorRoleId)->value('user_id');

        // 3. جلب معرف الغرفة
        $roomId = DB::table('rooms')->value('id');

        // 4. جلب معرفات العلاجات (وإنشاء علاجات افتراضية فوراً إذا كان الجدول فارغاً)
        $treatmentIds = DB::table('treatments')->pluck('id')->toArray();
        if (empty($treatmentIds)) {
            foreach (['تقشير كيميائي', 'هيدرافيشيال', 'حقن بوتوكس', 'فيلر شفايف', 'ميزوثيرابي'] as $name) {
                $treatmentIds[] = DB::table('treatments')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 🛡️ حماية الدكتور
        if (!$doctorId) {
            if (!$doctorRoleId) {
                $doctorRoleId = DB::table('roles')->insertGetId(['name' => 'doctor', 'created_at' => now(), 'updated_at' => now()]);
            }
            $doctorId = DB::table('users')->insertGetId([
                'name' => 'د. رنا الخطيب', 'email' => 'doctor@clinic.com', 'password' => Hash::make('password123'), 'created_at' => now(), 'updated_at' => now()
            ]);
            DB::table('role_user')->insert(['user_id' => $doctorId, 'role_id' => $doctorRoleId]);
        }

        // 🛡️ حماية الغرفة
        if (!$roomId) {
            $roomId = DB::table('rooms')->insertGetId(['name' => 'غرفة الفحص العامة', 'created_at' => now(), 'updated_at' => now()]);
        }

        // 💡 حددي هنا اسم جدول الربط الفعلي عندكِ (تأكدي إذا كان ينتهي بـ s أو لا من ملف الميجريشن)
        $pivotTable = 'appointment_treatment'; 

        // بدء توليد البيانات
        if (!empty($patients)) {
            for ($i = 0; $i < 35; $i++) {
                $currentPatient = ($i < 5) ? $patients[0] : fake()->randomElement($patients);

                // أ) إنشاء الموعد
                $appointmentId = DB::table('appointments')->insertGetId([
                    'patient_id'       => $currentPatient->id,
                    'user_id'          => $currentPatient->user_id, 
                    'doctor_id'        => $doctorId, 
                    'room_id'          => $roomId, 
                    'appointment_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d H:i:s'),
                    'status'           => fake()->randomElement(['pending', 'completed']), 
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                // ب) ربط الموعد بعلاجات عشوائية في جدول الـ Pivot
                $randomTreatments = fake()->randomElements($treatmentIds, fake()->numberBetween(1, 2));
                foreach ($randomTreatments as $treatmentId) {
                    DB::table($pivotTable)->insert([
                        'appointment_id' => $appointmentId,
                        'treatment_id'   => $treatmentId,
                    ]);
                }

                // ج) إنشاء الجلسة
                $sessionId = DB::table('clinic_sessions')->insertGetId([
                    'appointment_id' => $appointmentId,
                    'created_at'     => fake()->dateTimeBetween('-1 month', 'now'),
                    'updated_at'     => now(),
                ]);

                // د) إنشاء الفاتورة
                DB::table('bills')->insert([
                    'clinic_session_id' => $sessionId,
                    'amount_paid'       => fake()->randomFloat(2, 50000, 950000), 
                    'date'              => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'), 
                    'status'            => fake()->randomElement(['paid', 'unpaid']),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }
}