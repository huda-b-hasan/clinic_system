<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب الأدوار من قاعدة البيانات لنتمكن من ربطها
        $managerRole = Role::where('name', 'Manager')->first();
        $doctorRole = Role::where('name', 'Doctor')->first();
        $receptionistRole = Role::where('name', 'Receptionist')->first();

        // 2. إنشاء مستخدم مديـر وتعيين دور المدير له
        $manager = User::updateOrCreate(
            ['email' => 'manager@clinic.com'],
            [
                'name' => 'Dr. Hala (Manager)',
                'password' => Hash::make('password'),
            ]
        );
        if ($managerRole) {
            // التعديل هنا: يضمن جلب الـ ID الصحيح سواء كان اسمه id أو role_id
            $manager->roles()->sync([$managerRole->role_id ?? $managerRole->id]); 
        }

        // 3. إنشاء مستخدم طبيب وتعيين دور الطبيب له
        $doctor = User::updateOrCreate(
            ['email' => 'doctor@clinic.com'],
            [
                'name' => 'John Doe (Doctor)',
                'password' => Hash::make('password'),
            ]
        );
        if ($doctorRole) {
            $doctor->roles()->sync([$doctorRole->role_id ?? $doctorRole->id]);
        }

        // 4. إنشاء مستخدم موظف استقبال
        $receptionist = User::updateOrCreate(
            ['email' => 'reception@clinic.com'],
            [
                'name' => 'Jane Smith',
                'password' => Hash::make('password'),
            ]
        );
        if ($receptionistRole) {
            $receptionist->roles()->sync([$receptionistRole->role_id ?? $receptionistRole->id]);
        }
    }
}