<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TreatmentSeeder extends Seeder
{
    public function run(): void
    {
        $treatments = [
            // [
            //     'name' => 'بوتوكس كامل الوجه', 
            //     'description' => 'جلسة حقن البوتوكس للتخلص من تجاعيد الجبهة وحول العينين بالكامل.', 
            //     'base_price' => 250.00, 
            //     'discount_price' => 220.00, // مضاف
            //     'category' => 'Injections', 
            //     'duration' => 30,
            //     'image' => 'auth/images/botox.png' // مضاف
            // ],
            [
                'name' => 'فيلر شفايف 1 مل', 
                'description' => 'تعبئة وتكبير الشفايف وتصحيح التناظر باستخدام حمض الهيالورونيك.', 
                'base_price' => 180.00, 
                'discount_price' => null, // بدون خصم
                'category' => 'Injections', 
                'duration' => 45,
                'image' => 'auth/images/lips_filler.png'
            ],
            // [
            //     'name' => 'ليزر كامل الجسم', 
            //     'description' => 'جلسة إزالة الشعر بالليزر لجميع مناطق الجسم باستخدام جهاز كانديلا الحديث.', 
            //     'base_price' => 120.00, 
            //     'discount_price' => 99.00, 
            //     'category' => 'Laser', 
            //     'duration' => 60,
            //     'image' => '/home/hudahasan/Documents/clinic_system/public/auth/images/laser.png',
            // ],
            [
                'name' => 'تنظيف بشرة عميق (هيدرافيشيال)', 
                'description' => 'جلسة هيدرافيشيال طبية متكاملة لتنظيف المسام، تقشير خلايا الجلد الميت وترطيب البشرة.', 
                'base_price' => 60.00, 
                'discount_price' => 50.00, 
                'category' => 'Skin Care', 
                'duration' => 45,
                'image' => 'auth/images/hydrafacial.png'
            ],
            [
                'name' => 'ميزوثيرابي لنضارة الوجه', 
                'description' => 'حقن فيتامينات ومواد مغذية مخصصة لإعادة الحيوية، النضارة والإشراق للبشرة المتعبة.', 
                'base_price' => 85.00, 
                'discount_price' => null, 
                'category' => 'Injections', 
                'duration' => 30,
                'image' => 'auth/images/mesotherapy.png'
            ],
            [
                'name' => 'جلسة بلازما للوجه والشعر', 
                'description' => 'حقن البلازما الغنية بالصفائح الدموية (PRP) لتجديد خلايا البشرة وتحفيز نمو الشعر.', 
                'base_price' => 90.00, 
                'discount_price' => 75.00, 
                'category' => 'Skin Care', 
                'duration' => 45,
                'image' => 'auth/images/plasma.png'
            ],
            [
                'name' => 'ديرما بن (Dermapen)', 
                'description' => 'جلسة علاج ندبات حب الشباب، تحسين ملمس البشرة، وتضييق المسام الواسعة.', 
                'base_price' => 75.00, 
                'discount_price' => null, 
                'category' => 'Skin Care', 
                'duration' => 45,
                'image' => 'auth/images/dermapen.png'
            ],
            [
                'name' => 'تقشير كيميائي طبي للوجه', 
                'description' => 'تقشير طبي متخصص لتجديد خلايا الجلد والتخلص من التصبغات والبقع الداكنة.', 
                'base_price' => 70.00, 
                'discount_price' => 60.00, 
                'category' => 'Skin Care', 
                'duration' => 30,
                'image' => 'auth/images/peeling.png'
            ],
            [
                'name' => 'فيلر تحت العين (علاج الهالات)', 
                'description' => 'تعبئة تجاويف غائر العين للتخلص من المظهر المتعب وعلاج الهالات السوداء.', 
                'base_price' => 200.00,'discount_price' => null, 
                'category' => 'Injections', 
                'duration' => 45,
                'image' => 'auth/images/under_eye_filler.png'
            ],
            [
                'name' => 'التقشير الكربوني بالليزر', 
                'description' => 'جلسة ليزر كربوني (هوليوود بيل) لنضارة فورية، تنظيف البشرة وتفتيحها.', 
                'base_price' => 80.00, 
                'discount_price' => 65.00, 
                'category' => 'Laser', 
                'duration' => 45,
                'image' => 'auth/images/carbon_peel.png'
            ],
        ];

        foreach ($treatments as $treatment) {
            DB::table('treatments')->insert([
                'name' => $treatment['name'],
                'description' => $treatment['description'],
                'base_price' => $treatment['base_price'],
                'discount_price' => $treatment['discount_price'], 
                'category' => $treatment['category'], 
                'duration' => $treatment['duration'], 
                'image' => $treatment['image'], 
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}