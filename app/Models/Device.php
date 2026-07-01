<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'model', 'status', 'last_maintenance'];

    public function treatments() { return $this->belongsToMany(Treatment::class, 'device_treatment'); }
}