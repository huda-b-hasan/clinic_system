<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'treatment_id', 'stars_number', 'comment'];

    public function user() { return $this->belongsTo(User::class); }
    
    public function treatment() { return $this->belongsTo(Treatment::class); }
}