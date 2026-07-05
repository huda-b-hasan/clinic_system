<?php


use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckAuth;
use Illuminate\Support\Facades\File;

use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\TreatmentController;
use App\Http\Controllers\API\RatingController;

use App\Http\Controllers\API\PatientSessionController;
use App\Http\Controllers\API\BillController;
use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\DoctorController;






use App\Http\Controllers\API\PatientController;

Route::get('/',function(){
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

Route::get('/doctors', [DoctorController::class, 'getAllDoctors']);// patients session

Route::middleware(CheckAuth::class)->group(function () {

    Route::get('/patient/profile', [PatientController::class, 'getPatientProfile']);

    Route::put('/appointments/{id}/cancel', [AppointmentController::class, 'cancelAppointment']);
    Route::get('/patient/sessions', [PatientSessionController::class, 'mySessions']);
    Route::get('/patient/appointments', [PatientController::class, 'getAppointments']);
    Route::get('/patient/dashboard-data', [PatientController::class, 'getPatientDashboardData']);
    Route::get('/patient/check-pending-rating', [PatientController::class, 'checkPendingRating']);
        Route::get('/patient/recentTreatments', [PatientController::class, 'getRecentTreatmentsForRating']);

   // store ratting
    Route::post('/ratings', [RatingController::class, 'store']);
    //bills
      Route::get('/patient/pending-bills/count', [BillController::class, 'getPendingBillsCount']);

    Route::get('/patient/bills', [BillController::class, 'getBillDataPatient']);
    //appoointment 
    Route::post('/appointments', [AppointmentController::class, 'storeAppointment']);
    });
//  rating
Route::get('/ratings/{id}', [RatingController::class, 'getTreatmentRatings']);
Route::get('/ratings', [RatingController::class, 'index']);

//profile

Route::middleware([\App\Http\Middleware\CheckAuth::class])->group(function () {

    Route::get('/profile', [ProfileController::class, 'show']);

    Route::put('/profile/update', [ProfileController::class, 'update']);

});
