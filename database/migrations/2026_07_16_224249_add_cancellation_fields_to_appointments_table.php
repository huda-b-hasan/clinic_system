<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // إضافة حقل لتحديد مكان الإلغاء بعد حقل الحالة status
            $table->string('cancelled_via')->nullable()->after('status')
                  ->comment('Where the appointment was cancelled: app, clinic, phone, system, etc.');
            
            // حقل إضافي اختياري لتسجيل سبب الإلغاء
            $table->text('cancellation_reason')->nullable()->after('cancelled_via');
            
            // حقل اختياري لتسجيل تاريخ وقت الإلغاء بالضبط
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['cancelled_via', 'cancellation_reason', 'cancelled_at']);
        });
    }
};