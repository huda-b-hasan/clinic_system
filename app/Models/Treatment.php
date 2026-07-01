<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory;
protected $fillable = [
        'name',
        'description',
        'image',
        'base_price',
        'discount_price',
        'duration',
        'category',
        'status',
        'features' //new
    ];

    protected $casts = [
        'features' => 'array',
    ];

    // العلاج يمكن أن يستخدم كذا جهاز
    public function devices() { return $this->belongsToMany(Device::class, 'device_treatment'); }

    // العلاج يستهلك كذا مادة
    public function materials() { return $this->belongsToMany(Material::class, 'material_treatment'); }

    public function appointments() { return $this->belongsToMany(Appointment::class, 'appointment_treatment'); }

    public function ratings() { return $this->hasMany(Rating::class); }
}
