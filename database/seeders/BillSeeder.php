<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ClinicSessions;
use App\Models\Bill;

class BillSeeder extends Seeder
{
    public function run(): void
    {
        // 1. جلب كافة الجلسات لضمان تغطية كل الجلسات الموجودة
        $sessions = ClinicSessions::all();

        if ($sessions->isNotEmpty()) {
            foreach ($sessions as $session) {
                
                // تحديد حالة دفع منطقية
                $status = fake()->randomElement(['paid', 'unpaid', 'partially_paid']);
                
                // حساب مبلغ منطقي بناءً على الحالة
                $amount = fake()->randomElement([150.00, 300.00, 500.00, 750.00]);
                $paid = ($status === 'paid') ? $amount : (($status === 'partially_paid') ? ($amount / 2) : 0.00);

                // استخدام updateOrCreate لمنع التكرار (في حال تم تشغيل السييدر أكثر من مرة)
                Bill::updateOrCreate(
                    ['clinic_session_id' => $session->id],
                    [
                        'amount_paid' => $paid,
                        'date'        => $session->created_at->format('Y-m-d'), // الفاتورة بتاريخ الجلسة
                        'status'      => $status,
                        'created_at'  => $session->created_at,
                        'updated_at'  => now(),
                    ]
                );
            }
        }
    }
}