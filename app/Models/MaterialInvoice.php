<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'quantity_added',
        'unit_price',
        'total_price',
        'invoice_date',
    ];

    /**
     * العلاقة: الفاتورة تنتمي لمادة واحدة
     */
    public function material()
    {
        return $table = $this->belongsTo(Material::class);
    }
}