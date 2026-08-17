<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PromoCodePatientSeeder extends Seeder
{
    public function run(): void
    {
        $patients = DB::table('patients')->pluck('id')->toArray();
        $promoCodes = DB::table('promo_codes')->where('is_active', true)->pluck('id')->toArray();

        if (!empty($patients) && !empty($promoCodes)) {
            // محاكاة استخدام بعض المرضى للأكواد
            for ($i = 0; $i < 15; $i++) {
                $patientId = fake()->randomElement($patients);
                $promoCodeId = fake()->randomElement($promoCodes);

                // استخدام updateOrInsert لمنع تكرار نفس الزوج (patient_id, promo_code_id)
                DB::table('promo_code_patient')->updateOrInsert(
                    ['patient_id' => $patientId, 'promo_code_id' => $promoCodeId],
                    ['used_at' => now()]
                );
            }
        }
    }
}