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
        // 1. جلب المواعيد الموجودة مسبقاً مع العلاقة
        $appointments = Appointment::with('treatments')->get();

        // 2. التحقق من وجود أطباء
        $doctorIds = User::doctor()->pluck('id')->toArray();
        if (empty($doctorIds)) {
            $doctorRole = Role::firstOrCreate(['name' => 'Doctor']);
            $defaultDoctors = [
                ['name' => 'هلا فندو', 'email' => 'dr.hala@clinic.com', 'phone' => '0922222222'],
                ['name' => 'د. سارة الأحمد', 'email' => 'dr.sara@clinic.com', 'phone' => '09999999999'],
            ];

            foreach ($defaultDoctors as $doc) {
                $newDoc = User::updateOrCreate(
                    ['email' => $doc['email']],
                    [
                        'name' => $doc['name'],
                        'phone' => $doc['phone'],
                        'password' => Hash::make('12341234'),
                    ]
                );
                $newDoc->roles()->sync([$doctorRole->role_id ?? $doctorRole->id]);
                $doctorIds[] = $newDoc->id;
            }
        }

        $room = Room::first();
        $roomId = $room ? ($room->room_id ?? $room->id) : Room::create(['name' => 'غرفة رقم 1', 'type' => 'الحقن التجميلي', 'status' => 'available'])->id;

        $treatments = Treatment::all();
        $treatmentIds = $treatments->pluck('id')->toArray();
        $treatmentList = $treatments->keyBy('id')->toArray();

        $patients = Patient::all();

        // في حال لم تكن هناك مواعيد كافية، ننشئها استدراكياً
        if ($appointments->isEmpty() && $patients->isNotEmpty() && !empty($doctorIds) && !empty($treatmentIds)) {
            for ($i = 0; $i < 25; $i++) {
                $patient = $patients->random();
                $doctorId = fake()->randomElement($doctorIds);

                $appointment = Appointment::create([
                    'patient_id'       => $patient->patient_id ?? $patient->id,
                    'user_id'          => $patient->user_id, 
                    'doctor_id'        => $doctorId, 
                    'room_id'          => $roomId, 
                    'appointment_date' => fake()->dateTimeBetween('-15 days', 'now')->format('Y-m-d H:i:s'),
                    'status'           => 'completed', 
                ]);

                $tId = fake()->randomElement($treatmentIds);
                $treatmentData = $treatmentList[$tId];
                $bookedPrice = $treatmentData['discount_price'] ?? $treatmentData['base_price'] ?? 100;

                $appointment->treatments()->attach($tId, [
                    'booked_price' => $bookedPrice
                ]);

                $appointments->push($appointment);
            }
        }

        // 3. المرور على المواعيد لإنشاء جلسات وفواتير باستخدام amount_paid
        foreach ($appointments as $appointment) {
            if (in_array($appointment->status, ['completed', 'confirmed'])) {
                
                $session = ClinicSessions::firstOrCreate(
                    ['appointment_id' => $appointment->id],
                    [
                        'doctor_notes' => 'تمت الجلسة بنجاح وتم تقديم التوجيهات الطبية للمريضة.',
                        'created_at' => $appointment->appointment_date,
                        'updated_at' => now(),
                    ]
                );

                $totalAmount = 0;
                if ($appointment->treatments->isNotEmpty()) {
                    foreach ($appointment->treatments as $treatment) {
                        $totalAmount += $treatment->pivot->booked_price ?? ($treatment->discount_price ?? $treatment->base_price);
                    }
                } else {
                    $totalAmount = 100.00;
                }

                $billStatus = ($appointment->status === 'completed') ? 'paid' : fake()->randomElement(['paid', 'unpaid']);
                $amountToStore = ($billStatus === 'paid') ? $totalAmount : 0.00;

                // استخدام الحقل 'amount_paid' المطابق للموديل والكونترولر
                Bill::updateOrCreate(
                    ['clinic_session_id' => $session->id],
                    [
                        'amount_paid' => $amountToStore, 
                        'date'        => date('Y-m-d', strtotime($appointment->appointment_date)), 
                        'status'      => $billStatus,
                        'created_at'  => $session->created_at,
                        'updated_at'  => now(),
                    ]
                );
            }
        }
    }
}