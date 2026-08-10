<?php

use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\API\BillController;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\PatientSessionController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\RatingController;
use App\Http\Controllers\API\TreatmentController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicSessionController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\RoomController;
use App\Http\Middleware\CheckAuth;
use App\Models\Patient;
use Illuminate\Http\Request;
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



Route::get('/treatments/{id}', [TreatmentController::class, 'show']);



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
    Route::patch('/appointments/{id}/arrived', [AppointmentController::class, 'markAsArrived']);
    Route::get('/receptionist/profile-data', [ReceptionController::class, 'getCurrentReceptionistProfile']);
    Route::get('/receptionist/bills-summary', [BillController::class, 'getBillsSummary']);
    Route::put('/bills/{id}/pay', [BillController::class, 'pay']);
});
Route::middleware([CheckAuth::class.':Doctor,Receptionist,Manager'])->group(function () {

    Route::get('/patients', [PatientController::class, 'index']);
    Route::patch('/appointments/{id}/start-session', [AppointmentController::class, 'startSession']);
    Route::get('/patients/{id}', [PatientController::class, 'show']);
    Route::post('/patients/update/{id}', [PatientController::class, 'update']);
    Route::post('/patients/add', [PatientController::class, 'store']);
    Route::get('/doctor/session-details/{appointmentId}', [ClinicSessionController::class, 'getSessionDetails']);
    Route::get('/materials/available', [MaterialController::class, 'getAvailableMaterials']);
    Route::post('/doctor/session-complete/{appointmentId}', [ClinicSessionController::class, 'completeSession']);
    Route::get('/patients/search', [PatientController::class, 'searchPatients']);
});

Route::middleware([CheckAuth::class.':Receptionist,Manager'])->group(function () {
    Route::get('/appointments/categorized', [AppointmentController::class, 'getCategorizedAppointments']);
    Route::get('/reception/rooms-status', [RoomController::class, 'getReceptionDashboard']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'updateAppointment']);
    // مسارات إدارة الغرف الأساسية
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::patch('/rooms/{id}/status', [RoomController::class, 'updateStatus']);

    Route::post('/validate-promocode', [AppointmentController::class, 'validatePromoCode']);
});
Route::middleware([CheckAuth::class.':Manager'])->group(function () {
    Route::get('/admin/dashboard-data', [AdminController::class, 'getDashboardData']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::apiResource('treatments', TreatmentController::class);
    Route::patch('treatments/{id}/toggle-status', [TreatmentController::class, 'toggleStatus']);
    Route::prefix('materials')->group(function () {
    Route::get('/', [MaterialController::class, 'index']); 
    Route::post('/', [MaterialController::class, 'store']); 
    Route::get('/{id}', [MaterialController::class, 'show']); 
    Route::put('/{id}', [MaterialController::class, 'update']);
    Route::delete('/{id}', [MaterialController::class, 'destroy']); 
    
    // عمليات المخزن الخاصة
    Route::post('/{id}/deduct', [MaterialController::class, 'deductQuantity']); 
    Route::post('/{id}/restock', [MaterialController::class, 'restock']);
});
});
Route::get('/get-patients-list', function (Request $request) {
    $q = trim($request->get('q', ''));

    if (empty($q)) {
        return response()->json(['status' => true, 'data' => []]);
    }

    $patients = Patient::where('name', 'LIKE', "%{$q}%")
        ->orWhere('phone', 'LIKE', "%{$q}%")
        ->limit(8)
        ->get(['id', 'name', 'phone', 'gender', 'birthdate', 'address', 'medical_notes']);

    return response()->json([
        'status' => true,
        'data' => $patients,
    ]);
});
