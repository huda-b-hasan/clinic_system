<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;


class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        // جلب البيانات الأساسية من الجداول الأخرى لتوزيعها على المواعيد
        $patients = Patient::all();
        $rooms = Room::all();

        // جلب المستخدمين حسب صلاحياتهم (الأطباء وموظفي الاستقبال)
        $doctors = User::whereHas('roles', function ($q) {
            $q->where('name', 'Doctor');
        })->get();

        $receptionists = User::whereHas('roles', function ($q) {
            $q->where('name', 'Receptionist');
        })->get();

        // التأكد من وجود بيانات كافية لمنع حدوث خطأ أثناء السيدر
        if ($patients->isNotEmpty() && $doctors->isNotEmpty() && $receptionists->isNotEmpty() && $rooms->isNotEmpty()) {

            // حلقة تكرارية لتوليد 20 موعداً عشوائياً (تتصرف كـ Factory مدمج)
            for ($i = 0; $i < 20; $i++) {

                // اختيار سجل عشوائي من كل مجموعة
                $patient = $patients->random();
                $doctor = $doctors->random();
                $receptionist = $receptionists->random();
                $room = $rooms->random();

                Appointment::create([
                    // دعم كلا الاحتمالين لمعرّف المريض بناءً على تصميم الجداول لديكِ
                    'patient_id' => $patient->patient_id ?? $patient->id,
                    'doctor_id' => $doctor->id,
                    'user_id' => $receptionist->id, // موظف الاستقبال الذي نسق الموعد
                    'room_id' => $room->room_id ?? $room->id,

                    // توليد تاريخ ووقت موعد عشوائي خلال الشهر القادم (بين الساعة 9 صباحاً و 8 مساءً)
                    'appointment_date' => Carbon::today()->format('Y-m-d').' '.fake()->randomElement(['09:00:00', '11:30:00', '14:00:00']),
                    // حالات المواعيد الطبية المستعملة في الأنظمة
                    'status' => fake()->randomElement(['pending', 'completed', 'canceled']),
                ]);
            }
        }
    }
}
