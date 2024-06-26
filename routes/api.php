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
    Route::resource('users', UsersController::class)->only(['index']);
    Route::resource('cabang', BranchController::class)->only(['index']);
    Route::resource('cr_application', CrAppilcationController::class)->only(['index']);
    Route::resource('kunjungan', CrSurveyController::class)->only(['index']);
    Route::get('kunjungan_admin', [CrSurveyController::class, 'showAdmins']);
    Route::get('fpk_kapos', [CrAppilcationController::class, 'showKapos']);
    Route::get('fpk_ho', [CrAppilcationController::class, 'showHo']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Route Group Master Menu
    Route::resource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);
    Route::resource('user_access_menu', UserAccessMenuController::class);

    // Route Group Users
    Route::resource('users', UsersController::class)->except(['index']);
    Route::post('image_upload_employee', [UsersController::class, 'uploadImage']);

    // Route Group Branch
    Route::resource('cabang', BranchController::class)->except(['index']);

    // Route Group Cr Application
    Route::resource('cr_application', CrAppilcationController::class)->except(['index']);
    Route::post('application_approval_kapos', [CrAppilcationController::class, 'approvalKapos']);
    Route::post('application_approval_ho', [CrAppilcationController::class, 'approvalHo']);
    Route::post('cr_application_generate', [CrAppilcationController::class, 'generateUuidFPK']);

    // Route Group Cr Prospek (Kunjungan)
    Route::resource('kunjungan', CrSurveyController::class)->except(['index', 'howAdmins']);
    Route::post('image_upload_prospect', [CrSurveyController::class, 'uploadImage']);

    // Detail Profile
    Route::get('me', [DetailProfileController::class, 'index']);
    Route::post('image_upload_personal', [DetailProfileController::class, 'uploadImage']);

    // Credit
   
});

Route::post('pk', [Credit::class, 'index']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
