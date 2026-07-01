<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClinicSessions;
use App\Models\Patient; 
use Illuminate\Http\Request;

class PatientSessionController extends Controller
{
    public function mySessions(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $userId = session('user_id');
                if ($userId) {
                    $user = \App\Models\User::find($userId);
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل دخوله أو انتهت صلاحية الجلسة.'
                ], 401);
            }

            $patient = $user->patient ?? Patient::where('user_id', $user->id)->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على ملف مريض مرتبط بهذا الحساب.'
                ], 404);
            }

            $sessions = ClinicSessions::whereHas('appointment', function ($query) use ($patient) {
                $query->where('patient_id', $patient->id);
            })
            ->with([
                'appointment' => function ($query) {
                    $query->select('id', 'appointment_date', 'status', 'doctor_id')
                          ->with([
                              'doctor' => function($q) { $q->select('id', 'name'); },
                              'treatments' => function($q) { $q->select('treatments.id', 'name'); }
                          ]);
                },
                'bill'
            ])
            ->latest()
            ->get();

            return response()->json([
                'status' => "success",
                'count' => $sessions->count(),
                'data' => $sessions
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'error_type' => 'Server Exception Caught',
                'message' => $e->getMessage(), 
                'file' => $e->getFile(),      
                'line' => $e->getLine()      
            ], 500);
        }
    }
}