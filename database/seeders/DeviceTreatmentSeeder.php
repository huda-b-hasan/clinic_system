<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceTreatmentSeeder extends Seeder
{
    public function run(): void
    {
        $devices = DB::table('devices')->pluck('id')->toArray();
        $treatments = DB::table('treatments')->pluck('id')->toArray();

        if (!empty($devices) && !empty($treatments)) {
            foreach ($treatments as $treatmentId) {
                // ربط كل خدمة بجهاز أو جهازين عشوائياً
                $randomDevices = fake()->randomElements($devices, fake()->numberBetween(1, 2));
                foreach ($randomDevices as $deviceId) {
                    DB::table('device_treatment')->updateOrInsert(
                        ['device_id' => $deviceId, 'treatment_id' => $treatmentId]
                    );
                }
            }
        }
    }
}