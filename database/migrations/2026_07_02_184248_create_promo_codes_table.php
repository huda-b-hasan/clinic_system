<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('discount_type', ['percentage', 'fixed']); // نوع الخصم
            $table->decimal('discount_value', 8, 2); // قيمة الخصم
            
            $table->foreignId('treatment_id')->nullable()->constrained('treatments')->onDelete('cascade');
            
            $table->date('expiry_date'); 
            $table->integer('usage_limit')->nullable(); 
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};