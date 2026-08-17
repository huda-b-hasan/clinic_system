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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('doctor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('room_id')->nullable()->constrained()->onDelete('set null');
            $table->dateTime('appointment_date');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
