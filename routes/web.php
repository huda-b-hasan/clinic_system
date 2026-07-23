<?php

use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\API\BillController;
use App\Http\Controllers\ClinicSessionController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\PatientSessionController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\TreatmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\ReceptionController;
use App\Http\Middleware\CheckAuth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return File::get(public_path('auth/home.html'));
    });
Route::get('/login', function () {
    return File::get(public_path('auth/login.html'));
});

Route::get('/register', function () {
    return File::get(public_path('auth/register.html'));
});

Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', [AuthController::class, 'register']);

// treatments
Route::get('/treatments', [TreatmentController::class, 'index']);

Route::post('/treatments', [TreatmentController::class, 'store']);

Route::get('/treatments/{id}', [TreatmentController::class, 'show']);

Route::put('/treatments/{id}', [TreatmentController::class, 'update']);

Route::delete('/treatments/{id}', [TreatmentController::class, 'destroy']);

// doctor

Route::get('/doctors', [DoctorController::class, 'getAllDoctors']); // patients session

Route::middleware(CheckAuth::class)->group(function () {
    Route::post('/appointments/{id}/mark-as-seen', [AppointmentController::class, 'markAsSeen']);
    Route::put('/appointments/{id}/cancel', [AppointmentController::class, 'cancelAppointment']);

    Route::get('/patient/profile', [PatientController::class, 'getPatientProfile']);
    Route::get('/patient/sessions', [PatientSessionController::class, 'mySessions']);
    Route::get('/patient/appointments', [PatientController::class, 'getAppointments']);
    Route::get('/patient/dashboard-data', [PatientController::class, 'getPatientDashboardData']);
    Route::get('/patient/check-pending-rating', [PatientController::class, 'checkPendingRating']);
    Route::get('/patient/recentTreatments', [PatientController::class, 'getRecentTreatmentsForRating']);

    // store ratting
    Route::post('/ratings', [RatingController::class, 'store']);
    // bills
    Route::get('/patient/pending-bills/count', [BillController::class, 'getPendingBillsCount']);

    Route::get('/patient/bills', [BillController::class, 'getBillDataPatient']);
    // appoointment
    Route::post('/appointments', [AppointmentController::class, 'storeAppointment']);
});
//  rating
Route::get('/ratings/{id}', [RatingController::class, 'getTreatmentRatings']);
Route::get('/ratings', [RatingController::class, 'index']);

// profile

Route::middleware([CheckAuth::class])->group(function () {

    Route::get('/profile', [ProfileController::class, 'show']);

    Route::put('/profile/update', [ProfileController::class, 'update']);

});
// doctor
// لوحة تحكم الطبيب - محمية بالـ Middleware وتتحقق من صلاحية الطبيب
Route::middleware([CheckAuth::class.':Doctor'])->group(function () {

    Route::get('/doctor/dashboard-data', [DoctorController::class, 'getDashboardDoctor']);
    Route::get('/doctor/profile-data', [DoctorController::class, 'getCurrentDoctorProfile']);
    Route::get('/doctor/appointments', [DoctorController::class, 'getDoctorAppointments']);
});
Route::middleware([CheckAuth::class.':Receptionist'])->group(function () {

    Route::get('/receptionist/profile-data', [ReceptionController::class, 'getCurrentReceptionistProfile']);
    Route::get('/receptionist/bills-summary', [BillController::class, 'getBillsSummary']);
    Route::put('/bills/{id}/pay', [BillController::class, 'pay']);
});
Route::middleware([CheckAuth::class . ':Doctor,Receptionist'])->group(function () {

    Route::get('/patients', [PatientController::class, 'index']);
    
    Route::get('/patients/{id}', [PatientController::class, 'show']);
    Route::post('/patients/update/{id}', [PatientController::class, 'update']);
    Route::post('/patients/add', [PatientController::class, 'store']);
    Route::get('/doctor/session-details/{appointmentId}', [ClinicSessionController::class, 'getSessionDetails']);
    Route::get('/materials/available', [MaterialController::class, 'getAvailableMaterials']);
    Route::post('/doctor/session-complete/{appointmentId}', [ClinicSessionController::class, 'completeSession']);
});

