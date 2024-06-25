<?php

use App\Http\Controllers\API\{
    AuthController,
    BranchController,
    CrAppilcationController,
    Credit,
    CrSurveyController,
    DetailProfileController,
    MasterMenuController,
    UserAccessMenuController,
    UsersController
};
use App\Http\Controllers\Welcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Login Authenticate
Route::post('auth/login', [AuthController::class, 'login'])->name('login');
Route::get('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('welcome', [Welcome::class, 'index']);

Route::middleware(['auth:sanctum', 'check.access'])->group(function () {
    //Route Group Master Users
    Route::apiResource('users', UsersController::class);

    //Route Group Master Branch
    Route::get('cabang', [BranchController::class, 'index']);

    //Route Group Master Cr Application
    Route::apiResource('cr_application', CrAppilcationController::class);

    //Route Group Cr Prospek (Kunjungan)
    Route::apiResource('kunjungan', CrSurveyController::class);
    Route::get('kunjungan_admin', [CrSurveyController::class, 'showAdmins']);

    Route::get('pk_number/{id}', [Credit::class, 'index']);

    Route::get('fpk_kapos', [CrAppilcationController::class, 'showKapos']);
    Route::get('fpk_ho', [CrAppilcationController::class, 'showHo']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);
    Route::apiResource('user_access_menu', UserAccessMenuController::class);

    Route::get('cabang/{id}', [BranchController::class, 'show']); // GET a single branch
    Route::post('cabang', [BranchController::class, 'store']); // POST a new branch
    Route::put('cabang/{id}', [BranchController::class, 'update']); // PUT to update a branch
    Route::delete('cabang/{id}', [BranchController::class, 'destroy']); 

    //Detail Profile
    Route::get('me', [DetailProfileController::class, 'index']);

    Route::post('image_upload_employee', [UsersController::class, 'uploadImage']);
    Route::post('image_upload_personal', [DetailProfileController::class, 'uploadImage']);

    Route::post('application_approval_kapos', [CrAppilcationController::class, 'approvalKapos']);
    Route::post('application_approval_ho', [CrAppilcationController::class, 'approvalHo']);

     // Route::get('cr_applications', [CrAppilcationController::class, 'show']);
     Route::post('cr_application_generate', [CrAppilcationController::class, 'generateUuidFPK']);
     Route::post('image_upload_prospect', [CrSurveyController::class, 'uploadImage']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
