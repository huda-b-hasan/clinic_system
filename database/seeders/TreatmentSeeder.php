<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TreatmentSeeder extends Seeder
{
    public function run(): void
    {
        $treatments = [
            [
                'name' => 'إزالة الشعر بالليزر كامل الجسم',
                'description' => 'جلسة إزالة شعر متكاملة باستخدام أحدث أجهزة الليزر مع تقنية التبريد.',
                'base_price' => 500.00,
                'discount_price' => 400.00,
                'duration' => 60,
                'category' => 'ليزر',
                'status' => 'active',
                'image' => 'auth/images/laser_ body.png',
                'features' => ['تقنية تبريد متطورة', 'بدون ألم تقريباً', 'نتائج طويلة الأمد']
            ],
            [
                'name' => 'حقن بوتوكس للوجه',
                'description' => 'علاج تجاعيد الجبهة وحول العينين بنتائج طبيعية ومميزة.',
                'base_price' => 300.00,
                'discount_price' => null,
                'duration' => 30,
                'category' => 'حقن تجميلي',
                'status' => 'active',
                'image' => 'auth/images/dermapen.png',
                'features' => ['نتائج طبيعية', 'إجراء سريع (لا يتطلب وقتاً للتعافي)', 'مواد مرخصة وآمنة']
            ],
            [
                'name' => 'جلسة هيدرافيشيال (تنظيف بشرة عميق)',
                'description' => 'تنظيف، تقشير، ترطيب وتغذية البشرة بأحدث التقنيات.',
                'base_price' => 200.00,
                'discount_price' => 175.00,
                'duration' => 45,
                'category' => 'عناية بالبشرة',
                'status' => 'active',
                'image' => 'auth/images/hydrafacial.png',
                'features' => ['تنظيف المسام بعمق', 'نضارة فورية للبشرة', 'مناسب لجميع أنواع البشرة']
            ],
            [
                'name' => 'حقن فيلر الشفاه',
                'description' => 'تحديد وتكبير الشفاه باستخدام أجود أنواع الفيلر المرخص.',
                'base_price' => 450.00,
                'discount_price' => 400.00,
                'duration' => 40,
                'category' => 'حقن تجميلي',
                'status' => 'active',
                'image' => 'auth/images/lips_filler.png',
                'features' => ['رسم وتحديد دقيق', 'مظهر طبيعي وجذاب', 'ترطيب عالي للمنطقة']
            ],
            [
                'name' => 'تقشير كربوني للنضارة',
                'description' => 'جلسة الليزر الكربوني لإزالة الرؤوس السوداء وتفتيح البشرة.',
                'base_price' => 150.00,
                'discount_price' => 120.00,
                'duration' => 30,
                'category' => 'ليزر',
                'status' => 'active',
                'image' => 'auth/images/facial_care.png',
                'features' => ['إزالة الخلايا الميتة', 'تصغير المسام الواسعة', 'تفتيح وتوحيد لون البشرة']
            ],
            [
                'name' => 'نحت الجسم (تخسيس موضعي)',
                'description' => 'جلسة تفتيت الدهون الموضعية باستخدام أجهزة النحت المتطورة.',
                'base_price' => 600.00,
                'discount_price' => 500.00,
                'duration' => 50,
                'category' => 'تخسيس',
                'status' => 'active',
                'image' => 'auth/images/peeling.png',
                'features' => ['تفتيت الدهون المتركزة', 'شد الجلد وتحسين ملمسه', 'بدون جراحة أو تدخل جراحي']
            ],
        ];

        foreach ($treatments as $treatment) {
            DB::table('treatments')->insert([
                'name'           => $treatment['name'],
                'description'    => $treatment['description'],
                'base_price'     => $treatment['base_price'],
                'discount_price' => $treatment['discount_price'],
                'duration'       => $treatment['duration'],
                'category'       => $treatment['category'],
                'status'         => $treatment['status'],
                'image'          => $treatment['image'],
                'features'       => json_encode($treatment['features'], JSON_UNESCAPED_UNICODE),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
}