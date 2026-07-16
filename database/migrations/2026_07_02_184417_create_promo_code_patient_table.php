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
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('promo_code_id')->constrained('promo_codes')->onDelete('cascade');
            $table->timestamp('used_at')->useCurrent();

            $table->unique(['patient_id', 'promo_code_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_patient');
    }
};