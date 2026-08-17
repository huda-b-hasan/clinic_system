<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicSessionMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $sessions = DB::table('clinic_sessions')->pluck('id')->toArray();
        $materials = DB::table('materials')->get()->keyBy('id')->toArray();

        if (!empty($sessions) && !empty($materials)) {
            foreach ($sessions as $sessionId) {
                // ربط كل جلسة بـ 1 إلى 2 مواد مستهلكة
                $randomMaterialIds = fake()->randomElements(array_keys($materials), fake()->numberBetween(1, 2));
                
                foreach ($randomMaterialIds as $matId) {
                    $material = $materials[$matId];
                    
                    DB::table('clinic_session_material')->insert([
                        'clinic_session_id' => $sessionId,
                        'material_id'       => $matId,
                        'quantity'          => fake()->numberBetween(1, 2),
                        'unit_price'        => $material->unit_price,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }
        }
    }
}