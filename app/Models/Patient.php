<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'phone', 'gender', 'birthdate', 'address', 'medical_notes'];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function usedPromoCodes()
    {
        return $this->belongsToMany(PromoCode::class, 'promo_code_patient')
            ->withTimestamps()
            ->withPivot('used_at');
    }
}
