<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use App\Models\Treatment;
use Carbon\Carbon;

class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب البيانات الأساسية من الجداول المرتبطة
        $patients = Patient::all();
        $rooms = Room::all();
        $treatments = Treatment::all();

        // 2. جلب الأطباء
        $doctors = User::whereHas('roles', function ($q) {
            $q->where('name', 'Doctor');
        })->get();

        // 3. جلب موظفي الاستقبال
        $receptionists = User::whereHas('roles', function ($q) {
            $q->where('name', 'Receptionist');
        })->get();

        // التأكد من توفر البيانات الأساسية لتفادي أي أخطاء
        if ($patients->isNotEmpty() && $doctors->isNotEmpty() && $rooms->isNotEmpty() && $treatments->isNotEmpty()) {

            $timeSlots = ['09:00:00', '10:30:00', '12:00:00', '14:30:00', '16:00:00', '18:00:00'];

            // توليد 30 موعداً بتواريخ وحالات مختلفة
            for ($i = 0; $i < 30; $i++) {
                $patient = $patients->random();
                $doctor = $doctors->random();
                $receptionist = $receptionists->isNotEmpty() ? $receptionists->random() : null;
                $room = $rooms->random();
                $treatment = $treatments->random();

                // توزيع التواريخ: ماضي، اليوم، مستقبل
                $dateOffset = fake()->numberBetween(-10, 15); 
                $appointmentDate = Carbon::today()->addDays($dateOffset)->format('Y-m-d');
                $appointmentTime = fake()->randomElement($timeSlots);

                // تحديد الحالة بشكل منطقي بناءً على التاريخ
                $status = 'pending';
                if ($dateOffset < 0) {
                    $status = fake()->randomElement(['completed', 'cancelled']);
                } elseif ($dateOffset === 0) {
                    $status = fake()->randomElement(['pending', 'confirmed', 'completed']);
                } else {
                    $status = fake()->randomElement(['pending', 'confirmed']);
                }

                // أ) إنشاء الموعد (بدون حقل treatment_id لأنه غير موجود في الجدول)
                $appointment = Appointment::create([
                    'patient_id'       => $patient->patient_id ?? $patient->id,
                    'doctor_id'        => $doctor->id,
                    'user_id'          => $receptionist ? $receptionist->id : $doctor->id,
                    'room_id'          => $room->room_id ?? $room->id,
                    'appointment_date' => $appointmentDate . ' ' . $appointmentTime,
                    'status'           => $status,
                ]);

                // ب) ربط الموعد بالخدمة عبر جدول الربط الوسيط appointment_treatment بالطريقة الصحيحة
                $bookedPrice = $treatment->discount_price ?? $treatment->base_price;
                
                $appointment->treatments()->attach($treatment->id, [
                    'booked_price' => $bookedPrice
                ]);
            }
        }
    }
}