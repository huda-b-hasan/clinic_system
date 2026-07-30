<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'treatment_id',
        'expiry_date',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    // لتحويل حقل التاريخ تلقائياً إلى Carbon Instance لسهولة التعامل مع التواريخ
    protected $casts = [
        'expiry_date' => 'date',
        'is_active'   => 'boolean',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'promo_code_patient')
                    ->withPivot('used_at');
    }
}