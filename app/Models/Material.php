<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = ['material_name', 'quantity', 'unit_price'];

    public function treatments() { return $this->belongsToMany(Treatment::class, 'material_treatment'); }
}