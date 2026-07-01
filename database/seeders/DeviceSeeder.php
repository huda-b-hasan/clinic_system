<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // التعامل المباشر مع الجدول لسرعة وأمان العبور

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        // قائمة الأجهزة الطبية والتجميلية المشهورة في العيادات مصاغة باللغة العربية
        $devices = [
            ['name' => 'جهاز ليزر كانديلا جنتل برو الأصلي', 'model' => 'Candela-GentlePro-2024'],
            ['name' => 'جهاز هيدرافيشيال سينديو المتطور للبشرة', 'model' => 'Hydrafacial-Syndeo-V3'],
            ['name' => 'جهاز سبيكترا ليزر كربوني وتفتيح التصبغات', 'model' => 'Spectra-QSwitched-X'],
            ['name' => 'جهاز هايفو ألترا فورمر III لشد الوجه والرقبة', 'model' => 'Ultraformer-III'],
            ['name' => 'جهاز سيكرت ديو لإبر المايكرونيدل الراديوية', 'model' => 'Secret-Duo-RF'],
            ['name' => 'جهاز الـ بي جي (LPG) الفرنسي لنحت الجسم', 'model' => 'LPG-Endermologie-M6'],
            ['name' => 'جهاز ليزر كوانتا ديسكفري بيكو لعلاج الندبات', 'model' => 'Quanta-Discovery-Pico'],
            ['name' => 'جهاز فوتونا 4D المتكامل لعلاجات الجلد', 'model' => 'Fotona-4D-Laser'],
            ['name' => 'جهاز تبريد الجلد المصاحب لليزر (زيمر)', 'model' => 'Zimmer-Cryo-6'],
            ['name' => 'قناع ليد تيرابي الضوئي لعلاج حب الشباب', 'model' => 'LED-Therapy-Mask-Pro']
        ];

        // حلقة تكرار (Factory Mode) للمرور على الأجهزة وتوليد بياناتها بلمح البصر
        foreach ($devices as $device) {
            DB::table('devices')->insert([
                'name' => $device['name'],
                'model' => $device['model'],
                
                // حالات الأجهزة المعتمدة في كودكِ الأصلي (نشط / صيانة)
                'status' => fake()->randomElement(['active', 'maintenance']), 
                
                // توليد تاريخ صيانة عشوائي وموزع خلال الأشهر الستة الماضية لملء حقل last_maintenance
                'last_maintenance' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}