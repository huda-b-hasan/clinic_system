<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب الأدوار من قاعدة البيانات لنتمكن من ربطها
        $managerRole = Role::where('name', 'Manager')->first();
        $doctorRole = Role::where('name', 'Doctor')->first();
        $receptionistRole = Role::where('name', 'Receptionist')->first();

        // 2. إنشاء مستخدم مدير (هدى حسن)
        $manager = User::updateOrCreate(
            ['email' => 'manager@clinic.com'],
            [
                'name' => 'هدى حسن',
                'phone' => '0911111111',
                'password' => Hash::make('12341234'),
            ]
        );
        if ($managerRole) {
            $manager->roles()->sync([$managerRole->role_id ?? $managerRole->id]);
        }

        // 3. إنشاء مستخدم طبيب (هلا فندو)
        $doctor = User::updateOrCreate(
            ['email' => 'doctor@clinic.com'],
            [
                'name' => 'هلا فندو',
                'phone' => '0922222222',
                'password' => Hash::make('12341234'),
            ]
        );
        if ($doctorRole) {
            $doctor->roles()->sync([$doctorRole->role_id ?? $doctorRole->id]);
        }

        // 4. إنشاء مستخدم موظف استقبال (ديمة قسام)
        $receptionist = User::updateOrCreate(
            ['email' => 'reception@clinic.com'],
            [
                'name' => 'ديمة قسام',
                'phone' => '0933333333',
                'password' => Hash::make('12341234'),
            ]
        );
        if ($receptionistRole) {
            $receptionist->roles()->sync([$receptionistRole->role_id ?? $receptionistRole->id]);
        }


    }
}