<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;

class MaterialController extends Controller
{
    /**
     * جلب جميع المواد المتاحة في المخزن (التي كميتها أكبر من 0)
     */
    public function getAvailableMaterials(): JsonResponse
    {
        $materials = Material::where('quantity', '>', 0)
            ->select('id', 'name', 'unit_price', 'quantity')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $materials
        ], 200);
    }
}