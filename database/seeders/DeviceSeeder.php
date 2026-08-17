<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $devices = [
            ['name' => 'جهاز ليزر ألكسندريت', 'model' => 'Candela GentleMax Pro', 'status' => 'active', 'last_maintenance' => '2026-02-01'],
            ['name' => 'جهاز هيدرافيشيال', 'model' => 'HydraFacial MD Elite', 'status' => 'active', 'last_maintenance' => '2026-02-15'],
            ['name' => 'جهاز كربوني ليزر', 'model' => 'Q-Switched Nd:YAG', 'status' => 'active', 'last_maintenance' => '2026-01-10'],
            ['name' => 'جهاز نحت الجسم (كريو)', 'model' => 'CoolSculpting Elite', 'status' => 'maintenance', 'last_maintenance' => '2025-12-20'],
        ];

        foreach ($devices as $device) {
            DB::table('devices')->insert(array_merge($device, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}