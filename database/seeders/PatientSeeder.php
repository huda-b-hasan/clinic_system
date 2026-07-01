<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Hash; 
use App\Models\User; // 1. تأكدي من استدعاء موديل الـ User هنا

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        // 2. جلب معرّف صلاحية المريض من جدول الأدوار أولاً لتوفير الأداء داخل الـ loop
        // (افترضنا هنا أن اسم جدول الصلاحيات هو roles والاسم المخزن فيه هو 'patient')
        $patientRoleId = DB::table('roles')->where('name', 'patient')->value('id');

        // خطوة حماية: إذا لم تكن صلاحية patient موجودة في الجدول، ننشئها فوراً ونأخذ الـ id
        if (!$patientRoleId) {
            $patientRoleId = DB::table('roles')->insertGetId([
                'name' => 'patient',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $firstNames = ['أحمد', 'محمد', 'علي', 'عمر', 'حسام', 'خالد', 'باسل', 'ميساء', 'ريم', 'سارة', 'لين', 'نور', 'فاطمة', 'منى', 'هدى', 'رنا', 'زين', 'غيث'];
        $lastNames = ['المصطفى', 'ابراهيم', 'الخطيب', 'داوود', 'الحسن', 'العلي', 'حيدر', 'سليمان', 'رمضان', 'الناعم', 'الأسعد', 'يوسف'];
        $addresses = ['اللاذقية - المشروع السابع', 'اللاذقية - الزراعة', 'حلب - الشهباء', 'حمص - الإنشاءات', 'دمشق - المزة', 'اللاذقية - جبلة', 'طرطوس - الكورنيش', 'دمشق - مشروع دمر'];
        $medicalNotes = ['لا يوجد', 'يتناول مميعات دم (Asprin)', 'يتنحسس من التقشير الكيميائي المفرط', 'بشرة حساسة جداً', 'تحسس من بعض أنواع التخدير الموضعي', 'أجرى جلسات ليزر سابقة في عيادة أخرى'];

        for ($i = 0; $i < 50; $i++) {
            $gender = fake()->randomElement(['male', 'female']);
            $fullName = fake()->randomElement($firstNames) . ' ' . fake()->randomElement($lastNames);
            
            // إنشاء الحساب وجلب الـ ID الخاص به
            $userId = DB::table('users')->insertGetId([
                'name' => $fullName,
                'email' => 'patient' . ($i + 1) . '@clinic.com', 
                'password' => Hash::make('password123'), 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. السطر السحري: استدعاء اليوزر عبر الـ ID لربطه بالصلاحية باستخدام ميثود roles
            User::find($userId)->roles()->attach($patientRoleId);

            // إنشاء سجل المريض وربطه بالحساب
            DB::table('patients')->insert([
                'user_id' => $userId, 
                'name' => $fullName,
                'phone' => '09' . fake()->numberBetween(30000000, 99999999), 
                'gender' => $gender,
                'birthdate' => fake()->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'), 
                'address' => fake()->randomElement($addresses),
                'medical_notes' => fake()->randomElement($medicalNotes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}