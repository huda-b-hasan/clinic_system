<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    public function clinicSessions()
    {
        return $this->belongsToMany(ClinicSessions::class, 'clinic_session_material', 'material_id', 'clinic_session_id')
            ->withPivot('quantity', 'unit_price')
            ->withTimestamps();
    }

    protected $fillable = ['material_name', 'quantity', 'unit_price'];

    public function treatments()
    {
        return $this->belongsToMany(Treatment::class, 'material_treatment');
    }
}
