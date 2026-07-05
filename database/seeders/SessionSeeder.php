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

        // 4. جلب معرفات العلاجات (وإنشاء علاجات افتراضية فوراً إذا كان الجدول فارغاً مع أسعار افتراضية)
        $treatments = DB::table('treatments')->get();
        
        if ($treatments->isEmpty()) {
            $defaultTreatments = [
                ['name' => 'تقشير كيميائي', 'base_price' => 200.00, 'discount_price' => 150.00],
                ['name' => 'هيدرافيشيال', 'base_price' => 300.00, 'discount_price' => null],
                ['name' => 'حقن بوتوكس', 'base_price' => 500.00, 'discount_price' => 450.00],
                ['name' => 'فيلر شفايف', 'base_price' => 600.00, 'discount_price' => null],
                ['name' => 'ميزوثيرابي', 'base_price' => 250.00, 'discount_price' => 200.00],
            ];

            foreach ($defaultTreatments as $dt) {
                DB::table('treatments')->insert([
                    'name' => $dt['name'],
                    'base_price' => $dt['base_price'],
                    'discount_price' => $dt['discount_price'],
                    'duration' => 30, // مدة افتراضية بالدقائق
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // إعادة جلب العلاجات بعد إدخالها
            $treatments = DB::table('treatments')->get();
        }

        // تحويل العلاجات إلى مصفوفة Key-Value لتسهيل الوصول للأسعار بسرعة بالمعرّف
        $treatmentList = $treatments->keyBy('id')->toArray();
        $treatmentIds = $treatments->pluck('id')->toArray();

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

                // ب) ربط الموعد بعلاجات عشوائية وتمرير السعر المحجوز بدقة 🌟
                $randomTreatments = fake()->randomElements($treatmentIds, fake()->numberBetween(1, 2));
                foreach ($randomTreatments as $tId) {
                    // جلب بيانات العلاج الحالي لمعرفة سعره وقت الحجز
                    $treatmentData = $treatmentList[$tId];
                    
                    // تحديد السعر الفعلي (سعر الخصم إذا كان متوفراً، أو السعر الأساسي)
                    $bookedPrice = $treatmentData->discount_price ?? $treatmentData->base_price ?? 100.00;

                    DB::table($pivotTable)->insert([
                        'appointment_id' => $appointmentId,
                        'treatment_id'   => $tId,
                        'booked_price'   => $bookedPrice, // 👈 التعديل هنا: تم تمرير السعر بنجاح
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