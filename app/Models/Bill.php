<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = ['clinic_session_id', 'amount_paid', 'date'];

    public function clinicSession() { return $this->belongsTo(ClinicSessions::class,'clinic_session_id'); }
}