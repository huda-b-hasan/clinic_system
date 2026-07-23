<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicSessions extends Model
{
    use HasFactory;

    protected $fillable = ['appointment_id', 'doctor_notes'];

    public function appointment() { return $this->belongsTo(Appointment::class,"appointment_id"); }
public function materials()
{
    return $this->belongsToMany(Material::class, 'clinic_session_material', 'clinic_session_id', 'material_id')
                ->withPivot('quantity', 'unit_price')
                ->withTimestamps();
}
    // الجلسة الواحدة لها فاتورة واحدة
    public function bill() { return $this->hasOne(Bill::class,"clinic_session_id"); }
}