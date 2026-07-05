<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_patient', function (Blueprint $table) {
            $table->id();
            // ربط مع جدول المرضى اللي بعتيه
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            // ربط مع جدول الأكواد الجديد
            $table->foreignId('promo_code_id')->constrained('promo_codes')->onDelete('cascade');
            $table->timestamp('used_at')->useCurrent();

            // لمنع تكرار نفس المريض مع نفس الكود نهائياً في قاعدة البيانات
            $table->unique(['patient_id', 'promo_code_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_patient');
    }
};