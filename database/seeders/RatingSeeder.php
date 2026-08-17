<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RatingSeeder extends Seeder
{
    public function run(): void
    {
        // جلب المعرفات
        $userIds = DB::table('users')->pluck('id')->toArray();
        $treatmentIds = DB::table('treatments')->pluck('id')->toArray();

        // التعليقات
        $comments = [
            'الخدمة كانت ممتازة جداً، والتعامل راقٍ ومحترف.',
            'نتائج رائعة من أول جلسة، أنصح بالتعامل مع العيادة بشدة.',
            'المركز نظيف جداً والالتزام بالمواعيد ممتاز وبدون تأخير.',
            'الدكتورة متعاونة جداً وشرحت لي كل الخطوات بالتفصيل المريح.',
            'تجربة ممتازة والمعاملة من الموظفين مريحة ولطيفة للغاية.',
            'شغل احترافي والنتائج طبيعية تماماً ومثلما تمنيت.',
            'الأسعار مقبولة جداً مقارنة بجودة المواد والخدمة التوب.',
            'راضية تماماً عن النتيجة وسأكرر الزيارة بالتأكيد لمتابعة العناية.',
            'جلسة سريعة وغير مؤلمة والنتيجة بدأت بالظهور فوراً.'
        ];

        // التحقق من وجود بيانات للربط
        if (!empty($userIds) && !empty($treatmentIds)) {
            for ($i = 0; $i < 30; $i++) {
                DB::table('ratings')->insert([
                    'user_id' => fake()->randomElement($userIds),
                    'treatment_id' => fake()->randomElement($treatmentIds),
                    'stars_number' => fake()->numberBetween(3, 5),
                    'comment' => fake()->randomElement($comments),
                    'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}