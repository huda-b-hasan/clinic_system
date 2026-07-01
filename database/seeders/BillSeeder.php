<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BillSeeder extends Seeder
{
    public function run(): void
    {
        // جلب جميع السجلات المتاحة في جدول جلسات العيادة لتوزيع الفواتير عليها تكرارياً
        $sessions = DB::table('clinic_sessions')->get();

        // التأكد من وجود جلسات أولاً لتجنب أي خطأ أثناء الـ Seeding
        if ($sessions->isNotEmpty()) {
            
            foreach ($sessions as $session) {
                
                DB::table('bills')->insert([
                    // 💡 تم اعتماد $session->id كمعرّف صحيح ومباشر بناءً على ملاحظتكِ في السطر 17
                    'clinic_session_id' => $session->id, 
                    
                    // أسعار فواتير عشوائية وموزعة بناءً على أسعار الخدمات والعلاجات بالعيادة
                    'amount_paid' => fake()->randomElement([60.00, 85.00, 120.00, 150.00, 180.00, 250.00, 500.00]),
                    
                    // توليد تاريخ فاتورة عشوائي منطقي خلال الشهر الماضي وحتى اليوم
                    'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
                    
                    // حالات الدفع المعتمدة (مدفوع / غير مدفوع / مدفوع جزئياً)
                    'status' => fake()->randomElement(['paid', 'unpaid', 'partially_paid']),
                    
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}