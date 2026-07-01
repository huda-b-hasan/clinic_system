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

        // حلقة تكرار (Factory Mode) لتوليد 15 غرفة عشوائية ومكثفة بلمح البصر
        for ($i = 1; $i <= 15; $i++) {
            DB::table('rooms')->insert([
                'name' => 'غرفة رقم ' . $i, // توليد اسم الغرفة متسلسل ومنظم بالعربي (غرفة رقم 1، غرفة رقم 2...)
                
                // 💡 تنبيه بخصوص الحالة: طالعي الملاحظة الذكية بالأسفل
                'status' => fake()->randomElement(['available', 'busy']), 
                
                'type' => fake()->randomElement($roomTypes), // اختيار نوع الغرفة عشوائياً من القائمة العربية
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}