<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialTreatmentSeeder extends Seeder
{
    public function run(): void
    {
        $materials = DB::table('materials')->pluck('id')->toArray();
        $treatments = DB::table('treatments')->pluck('id')->toArray();

        if (!empty($materials) && !empty($treatments)) {
            foreach ($treatments as $treatmentId) {
                $randomMaterials = fake()->randomElements($materials, fake()->numberBetween(1, 3));
                foreach ($randomMaterials as $materialId) {
                    DB::table('material_treatment')->updateOrInsert(
                        ['material_id' => $materialId, 'treatment_id' => $treatmentId]
                    );
                }
            }
        }
    }
}