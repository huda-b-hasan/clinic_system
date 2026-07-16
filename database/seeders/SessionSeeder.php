<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Treatment;
use App\Models\Appointment;
use App\Models\ClinicSessions;
use App\Models\Bill;

class SessionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب سجلات المرضى باستخدام الـ Model
        $patients = Patient::select('id', 'user_id')->get()->toArray();
        
        // 2. جلب معرفات الأطباء باستخدام الـ Scope
        $doctorIds = User::doctor()->pluck('id')->toArray();

        // 3. إذا لم يكن هناك أطباء، ننشئهم باستخدام الـ Model مع إضافة رقم الهاتف
        if (empty($doctorIds)) {
            $doctorRole = Role::firstOrCreate(['name' => 'Doctor']);

            $defaultDoctors = [
                ['name' => 'د. رنا الخطيب', 'email' => 'dr.rana@clinic.com','phone'=>"09888696958"],
                ['name' => 'د. سارة الأحمد', 'email' => 'dr.sara@clinic.com','phone'=>"09999999999"],
                ['name' => 'د. أحمد منصور', 'email' => 'dr.ahmed@clinic.com','phone'=>"09886666958"],
            ];

            foreach ($defaultDoctors as $doc) {
                $newDoc = User::create([
                    'name' => $doc['name'],
                    'email' => $doc['email'],
                    'phone'=>$doc['phone'],
                    'password' => Hash::make('password123'),
                ]);
                
                $newDoc->roles()->attach($doctorRole->id);
                $doctorIds[] = $newDoc->id;
            }
        }

        // 4. جلب الغرفة أو إنشائها باستخدام موديل Room 🌟
        $room = Room::first();
        if (!$room) {
            $room = Room::create([
                'name' => 'غرفة الفحص العامة'
            ]);
        }
        $roomId = $room->id;

        // 5. جلب الخدمات أو إنشائها باستخدام موديل Treatment 🌟
        $treatments = Treatment::all();
        if ($treatments->isEmpty()) {
            $defaultTreatments = [
                ['name' => 'تقشير كيميائي', 'base_price' => 200.00, 'discount_price' => 150.00],
                ['name' => 'هيدرافيشيال', 'base_price' => 300.00, 'discount_price' => null],
                ['name' => 'حقن بوتوكس', 'base_price' => 500.00, 'discount_price' => 450.00],
            ];

            foreach ($defaultTreatments as $dt) {
                Treatment::create([
                    'name' => $dt['name'],
                    'base_price' => $dt['base_price'],
                    'discount_price' => $dt['discount_price'],
                    'duration' => 30,
                ]);
            }
            $treatments = Treatment::all();
        }

        $treatmentList = $treatments->keyBy('id')->toArray();
        $treatmentIds = $treatments->pluck('id')->toArray();

        // بدء توليد البيانات
        if (!empty($patients)) {
            for ($i = 0; $i < 35; $i++) {
                // تحويل المريض إلى كائن (Object) ليتوافق مع الاستدعاء بالسهم ->
                $currentPatient = (object) (($i < 5) ? $patients[0] : fake()->randomElement($patients));
                $randomDoctorId = fake()->randomElement($doctorIds);

                // أ) إنشاء الموعد باستخدام موديل Appointment
                $appointment = Appointment::create([
                    'patient_id'       => $currentPatient->id,
                    'user_id'          => $currentPatient->user_id, 
                    'doctor_id'        => $randomDoctorId, 
                    'room_id'          => $roomId, 
                    'appointment_date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d H:i:s'),
                    'status'           => fake()->randomElement(['pending', 'completed', 'canceled']), 
                ]);

                // ب) ربط الموعد بالعلاجات عشوائياً عبر علاقة الـ الـ Eloquent (Treatments)
                $randomTreatments = fake()->randomElements($treatmentIds, fake()->numberBetween(1, 2));
                foreach ($randomTreatments as $tId) {
                    $treatmentData = $treatmentList[$tId];
                    $bookedPrice = $treatmentData['discount_price'] ?? $treatmentData['base_price'] ?? 100.00;

                    // استخدام علاقة الموديل الجاهزة للربط بالجدول الوسيط
                    $appointment->treatments()->attach($tId, [
                        'booked_price' => $bookedPrice
                    ]);
                }

                // ج) إنشاء الجلسة باستخدام موديل ClinicSessions (بصيغة الجمع كما في مشروعكِ)
                $session = ClinicSessions::create([
                    'appointment_id' => $appointment->id,
                    'created_at'     => fake()->dateTimeBetween('-1 month', 'now'),
                ]);

                // د) إنشاء الفاتورة باستخدام موديل Bill
                Bill::create([
                    'clinic_session_id' => $session->id,
                    'amount_paid'       => fake()->randomFloat(2, 50, 950), 
                    'date'              => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'), 
                    'status'            => fake()->randomElement(['paid', 'unpaid']),
                ]);
            }
        }
    }
}