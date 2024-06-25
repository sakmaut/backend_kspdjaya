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
    Route::get('users', [UsersController::class, 'index']);

    //Route Group Master Branch
    Route::get('cabang', [BranchController::class, 'index']);

    //Route Group Master Cr Application
    Route::get('cr_application', [CrAppilcationController::class, 'index']);

    //Route Group Cr Prospek (Kunjungan)
    Route::get('kunjungan', [CrSurveyController::class, 'index']);
    Route::get('kunjungan_admin', [CrSurveyController::class, 'showAdmins']);

    Route::get('pk_number/{id}', [Credit::class, 'index']);

    Route::get('fpk_kapos', [CrAppilcationController::class, 'showKapos']);
    Route::get('fpk_ho', [CrAppilcationController::class, 'showHo']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);
    Route::apiResource('user_access_menu', UserAccessMenuController::class);

    Route::get('users/{id}', [UsersController::class, 'show']);
    Route::post('users', [UsersController::class, 'store']);
    Route::put('users/{id}', [UsersController::class, 'update']);
    Route::delete('users/{id}', [UsersController::class, 'destroy']); 

    Route::get('cabang/{id}', [BranchController::class, 'show']);
    Route::post('cabang', [BranchController::class, 'store']);
    Route::put('cabang/{id}', [BranchController::class, 'update']);
    Route::delete('cabang/{id}', [BranchController::class, 'destroy']); 

    Route::get('cr_application/{id}', [CrAppilcationController::class, 'show']);
    Route::post('cr_application', [CrAppilcationController::class, 'store']);
    Route::put('cr_application/{id}', [CrAppilcationController::class, 'update']);
    Route::delete('cr_application/{id}', [CrAppilcationController::class, 'destroy']); 

    Route::get('kunjungan/{id}', [CrSurveyController::class, 'show']);
    Route::post('kunjungan', [CrSurveyController::class, 'store']);
    Route::put('kunjungan/{id}', [CrSurveyController::class, 'update']);
    Route::delete('kunjungan/{id}', [CrSurveyController::class, 'destroy']); 

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
