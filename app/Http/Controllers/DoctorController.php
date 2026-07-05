<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;

class DoctorController extends Controller
{
    public function getAllDoctors()
    {
        try {
            $doctors = User::whereHas('roles', function ($query) {
                $query->where('name', 'Doctor'); 
            })
            ->select('id', 'name')
            ->get();

            return response()->json([
                'status' => true,
                'data' => $doctors
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في السيرفر: ' . $e->getMessage()
            ], 500);
        }
    }
    
}