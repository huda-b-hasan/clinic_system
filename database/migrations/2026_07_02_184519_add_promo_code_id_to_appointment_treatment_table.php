<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_treatment', function (Blueprint $table) {
            // إضافة حقل يربط بكود الخصم (بيكون Nullable إذا ما استخدم كود)
            $table->foreignId('promo_code_id')->nullable()->after('booked_price')->constrained('promo_codes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_treatment', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn('promo_code_id');
        });
    }
};