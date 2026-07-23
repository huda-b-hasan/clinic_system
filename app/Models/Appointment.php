<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'user_id',
        'room_id',
        'appointment_date',
        'status',
        'cancelled_via',
        'cancellation_reason',
        'cancelled_at',
        'patient_saw_cancellation',
        'cancellation_seen_at',

    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function clinicSession()
    {
        return $this->hasOne(ClinicSessions::class);
    }

    public function treatments()
    {
        return $this->belongsToMany(Treatment::class, 'appointment_treatment')
            ->withPivot('booked_price', 'promo_code_id');
    }
}
