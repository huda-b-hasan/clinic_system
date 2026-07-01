<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RatingSeeder extends Seeder
{
    public function run(): void
    {
        // جلب كل المعرفات (IDs) المتاحة في جدولي المستخدمين والعلاجات لربطها عشوائياً
        $userIds = DB::table('users')->pluck('id')->toArray();
        $treatmentIds = DB::table('treatments')->pluck('id')->toArray();

        // مصفوفة تعليقات مخصصة لتبدو المراجعات واقعية وتجعل لوحة التحكم مذهلة
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

        // التأكد من أن قاعدة البيانات تحتوي بالفعل على مستخدمين وعلاجات لمنع أي خطأ
        if (!empty($userIds) && !empty($treatmentIds)) {
            
            // يمكنك تغيير الرقم (30) لتوليد كمية التقييمات التي تفضلينها
            for ($i = 0; $i < 30; $i++) {
                DB::table('ratings')->insert([
                    'user_id' => fake()->randomElement($userIds),
                    'treatment_id' => fake()->randomElement($treatmentIds),
                    'stars_number' => fake()->numberBetween(3, 5), // توليد نجوم عشوائية بين 3 و 5 لتقييمات إيجابية واقعية
                    'comment' => fake()->randomElement($comments),   // اختيار تعليق عشوائي من المصفوفة
                    'created_at' => fake()->dateTimeBetween('-1 month', 'now'), // توزيع تواريخ التقييمات عشوائياً عبر الشهر الماضي
                    'updated_at' => now(),
                ]);
            }
        }
    }
}