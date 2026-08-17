<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // التعامل المباشر مع الجدول لسرعة وأمان العبور

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة أنواع الأقسام والغرف الطبية المتوفرة في العيادة باللغة العربية
        $roomTypes = [
            'إزالة الشعر بالليزر',
            'الحقن التجميلي (بوتوكس وفيلر)',
            'العناية بالبشرة والتقشير الهيدرافيشيال',
            'الاستشارات الطبية والكشف الفوري',
            'علاجات نحت الجسم والتخسيس الموضعي',
            'التقشير الكربوني وعلاجات ليزر النضارة'
        ];

        // حلقة تكرار لتوليد 15 غرفة متسلسلة ومنظمة
        for ($i = 1; $i <= 15; $i++) {
            DB::table('rooms')->insert([
                'name' => 'غرفة رقم ' . $i, // اسم الغرفة متسلسل ومنظم (غرفة رقم 1، غرفة رقم 2...)
                'status' => fake()->randomElement(['available', 'busy']), // حالة الغرفة (متاحة أو مشغولة)
                'type' => fake()->randomElement($roomTypes), // اختيار نوع الغرفة عشوائياً من القائمة الطبية
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}