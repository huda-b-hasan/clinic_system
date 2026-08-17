<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Hash; 
use App\Models\User;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب معرّف صلاحية المريض (Patient) من جدول الأدوار
        $patientRoleId = DB::table('roles')->where('name', 'Patient')->value('id');

        // إذا لم تكن موجودة، ننشئها فوراً ونأخذ الـ id
        if (!$patientRoleId) {
            $patientRoleId = DB::table('roles')->insertGetId([
                'name' => 'Patient',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. قوائم أسماء الإناث العربية والكنى والأعراس والبيانات الواقعية
        $firstNames = [
            'ميسم', 'سارة', 'لين', 'نور', 'فاطمة', 'منى', 'هدى', 'رنا', 'ريم', 'ميساء', 
            'ديمة', 'آية', 'تسنيم', 'بيان', 'سنيم', 'يارا', 'رغد', 'شهد', 'لجين', 'مروة', 
            'الماء', 'غزل', 'حلا', 'ساندي', 'دنا', 'سلاف', 'رؤى', 'بشرى', 'داليا', 'كندا'
        ];
        
        $lastNames = [
            'الحسن', 'العلي', 'حيدر', 'سليمان', 'رمضان', 'الناعم', 'الأسعد', 'يوسف', 
            'المصطفى', 'ابراهيم', 'الخطيب', 'داوود', 'قسام', 'فندو', 'طيفور', 'الشيخ'
        ];

        $addresses = [
            'اللاذقية - المشروع السابع', 
            'اللاذقية - الزراعة', 
            'اللاذقية - الكورنيش الغربي', 
            'اللاذقية - شارع العريض', 
            'اللاذقية - الشاغوري', 
            'طرطوس - الكورنيش', 
            'دمشق - المزة', 
            'حلب - الشهباء'
        ];

        $medicalNotes = [
            'لا يوجد ملاحظات طبية', 
            'بشرة حساسة جداً تجاه مواد التقشير', 
            'تحسس خفيف من بعض أنواع التخدير الموضعي', 
            'أجرت جلسات ليزر سابقة في مركز آخر', 
            'تعاني من جفاف موسمي في البشرة', 
            'لا توجد أمراض مزمنة'
        ];

        // 3. إنشاء 50 مريضة ببيانات دقيقة ومترابطة
        for ($i = 0; $i < 50; $i++) {
            $fullName = fake()->randomElement($firstNames) . ' ' . fake()->randomElement($lastNames);
            
            // تخصيص المريضة الأولى لتكون "ميسم عمر" التي ذكرتيها سابقاً لضمان توفرها بالموقع
            if ($i === 0) {
                $fullName = 'ميسم عمر';
            }

            // إنشاء الحساب في جدول users
            $userId = DB::table('users')->insertGetId([
                'name' => $fullName,
                'email' => 'patient' . ($i + 1) . '@clinic.com', 
                'phone' => '09' . fake()->numberBetween(30000000, 99999999), 
                'password' => Hash::make('12341234'), 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ربط الحساب بدور المريض في جدول role_user
            User::find($userId)->roles()->sync([$patientRoleId]);

            // إنشاء سجل في جدول patients وربطه بالـ user_id
            DB::table('patients')->insert([
                'user_id' => $userId, 
                'name' => $fullName,
                'phone' => '09' . fake()->numberBetween(30000000, 99999999), 
                'gender' => 'female', // جميعهن إناث
                'birthdate' => fake()->dateTimeBetween('-45 years', '-18 years')->format('Y-m-d'), 
                'address' => fake()->randomElement($addresses),
                'medical_notes' => fake()->randomElement($medicalNotes),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}