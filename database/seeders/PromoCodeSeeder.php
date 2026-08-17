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
        // جلب خدمات متنوعة لربط الأكواد الخاصة بها
        $firstTreatment = Treatment::skip(0)->first();
        $secondTreatment = Treatment::skip(1)->first();

        // 1. كود خصم عام بنسبة مئوية (20%) - صالح لمدة شهر وغير محدود الاستخدام (نشط)
        PromoCode::create([
            'code' => 'LAVENDER20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'treatment_id' => null, // عام لكل الخدمات
            'expiry_date' => Carbon::now()->addMonth(),
            'usage_limit' => null, // غير محدود
            'used_count' => 0,
            'is_active' => true,
        ]);

        // 2. كود خصم عام بقيمة ثابتة (50) - صالح لأول 10 مرضى فقط (نشط وقيد الاستخدام)
        PromoCode::create([
            'code' => 'WELCOME50',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'treatment_id' => null, // عام
            'expiry_date' => Carbon::now()->addWeeks(2),
            'usage_limit' => 10, 
            'used_count' => 3, // تم استخدامه 3 مرات
            'is_active' => true,
        ]);

        // 3. كود خصم مخصص لخدمة معينة فقط بنسبة (15%) (نشط)
        if ($firstTreatment) {
            PromoCode::create([
                'code' => 'SKIN15',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'treatment_id' => $firstTreatment->id, // مخصص لهي الخدمة بالذات
                'expiry_date' => Carbon::now()->addDays(10),
                'usage_limit' => 50,
                'used_count' => 5,
                'is_active' => true,
            ]);
        }

        // 4. كود خصم منتهي الصلاحية (Expired) - للتأكد من حظر استخدامه برمجياً
        PromoCode::create([
            'code' => 'EXPIRED2025',
            'discount_type' => 'percentage',
            'discount_value' => 30,
            'treatment_id' => null,
            'expiry_date' => Carbon::now()->subDays(5), // انتهى من 5 أيام
            'usage_limit' => 100,
            'used_count' => 12,
            'is_active' => true,
        ]);

        // 5. كود مستنفد بالكامل (Exhausted / Out of limit) - وصل للحد الأقصى للاستخدام
        PromoCode::create([
            'code' => 'FULL50',
            'discount_type' => 'fixed',
            'discount_value' => 25,
            'treatment_id' => $secondTreatment ? $secondTreatment->id : null,
            'expiry_date' => Carbon::now()->addDays(20),
            'usage_limit' => 5, 
            'used_count' => 5, // استنفد جميع مرات الاستخدام المسموحة
            'is_active' => true,
        ]);

        // 6. كود معطل أو غير نشط (Inactive / Disabled) - أوقفنه الإدارة يدوياً
        PromoCode::create([
            'code' => 'STOPPED10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'treatment_id' => null,
            'expiry_date' => Carbon::now()->addMonths(2),
            'usage_limit' => null,
            'used_count' => 2,
            'is_active' => false, // غير نشط
        ]);
    }
}