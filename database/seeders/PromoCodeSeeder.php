<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PromoCode;
use App\Models\Treatment;
use Carbon\Carbon;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        // نجيب أول خدمة موجودة بقاعدة البيانات عشان نعمل عليها كود مخصص للتجربة
        $treatment = Treatment::first();

        // 1. كود خصم عام بنسبة مئوية (20%) - صالح لمدة شهر وغير محدود الاستخدام
        PromoCode::create([
            'code' => 'LAVENDER20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'treatment_id' => null, // عام لكل الخدمات
            'expiry_date' => Carbon::now()->addMonth(),
            'usage_limit' => null, // غير محدود
            'used_count' => 0,
            'is_active' => true,
        ]);

        // 2. كود خصم عام بقيمة ثابتة (50 ليرة/دولار) - صالح لأول 10 مرضى فقط
        PromoCode::create([
            'code' => 'WELCOME50',
            'discount_type' => 'fixed',
            'discount_value' => 50.00,
            'treatment_id' => null, // عام
            'expiry_date' => Carbon::now()->addWeeks(2),
            'usage_limit' => 10, // لأول 10 بس
            'used_count' => 0,
            'is_active' => true,
        ]);

        // 3. كود خصم مخصص لخدمة معينة فقط بنسبة (15%) - إذا وُجدت خدمات بالقاعدة
        if ($treatment) {
            PromoCode::create([
                'code' => 'SPECIAL15',
                'discount_type' => 'percentage',
                'discount_value' => 15.00,
                'treatment_id' => $treatment->id, // مخصص لهي الخدمة بالذات
                'expiry_date' => Carbon::now()->addDays(7),
                'usage_limit' => 50,
                'used_count' => 0,
                'is_active' => true,
            ]);
        }
    }
}