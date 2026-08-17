<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; 

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة موسعة ومحترفة بكافة المستلزمات الطبية والتجميلية المستهلكة في العيادة
        $materials = [
            [
                'name' => 'أمبول بوتوكس (ألرغان الأصلي)',
                'quantity' => 45,
                'unit_price' => 120
            ],
            [
                'name' => 'حقنة فيلر جوفيديرم أولترا (1 مل)',
                'quantity' => 60,
                'unit_price' => 150
            ],
            [
                'name' => 'جل تبريد لجلسات الليزر (عبوة 5 لتر)',
                'quantity' => 25,
                'unit_price' => 30
            ],
            [
                'name' => 'محلول تقشير كيميائي طبي (أحماض فواكه)',
                'quantity' => 30,
                'unit_price' => 45
            ],
            [
                'name' => 'كريم تخدير موضعي عالي الفعالية (بريدوكاين)',
                'quantity' => 80,
                'unit_price' => 15
            ],
            [
                'name' => 'رؤوس وإبر جهاز الديرما بن المعقمة (علبة)',
                'quantity' => 50,
                'unit_price' => 25
            ],
            [
                'name' => 'خيوط شد الوجه والرقبة التجميلية (PDO Threads)',
                'quantity' => 40,
                'unit_price' => 95
            ],
            [
                'name' => 'سيروم هيدرافيشيال لتغذية وتنظيف مسام البشرة',
                'quantity' => 35,
                'unit_price' => 80
            ],
            [
                'name' => 'مسحات كحولية طبية معقمة (علبة 100 قطعة)',
                'quantity' => 120,
                'unit_price' => 10
            ],
            [
                'name' => 'قفازات طبية لاتكس خالية من البودرة (صندوق)',
                'quantity' => 90,
                'unit_price' => 20
            ],
            [
                'name' => 'أنابيب سحب وفصل البلازما الغنية بالصفائح (PRP Tubes)',
                'quantity' => 70,
                'unit_price' => 18
            ],
            [
                'name' => 'شاش طبي معقم وقطن تجميلي مضغوط للعيادات',
                'quantity' => 110,
                'unit_price' => 12
            ],
            [
                'name' => 'حقنة فيلر راديس (Radiesse) لتحفيز الكولاجين',
                'quantity' => 20,
                'unit_price' => 160
            ],
            [
                'name' => 'أمبولات ميزوثيرابي لتذويب دهون الذقن المزدوجة',
                'quantity' => 55,
                'unit_price' => 65
            ],
            [
                'name' => 'ماسك الطين البركاني لتهدئة البشرة بعد التقشير',
                'quantity' => 65,
                'unit_price' => 28
            ]
        ];

        // إدخال المواد إلى قاعدة البيانات مع كميات وأسعار منسقة
        foreach ($materials as $material) {
            DB::table('materials')->insert([
                'name' => $material['name'], 
                'quantity' => $material['quantity'], 
                'unit_price' => $material['unit_price'], 
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}