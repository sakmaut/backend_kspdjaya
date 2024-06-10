<?php

use App\Http\Controllers\API\{
    AuthController,
    BranchController,
    CrAppilcationController,
    CrprospectController,
    DetailProfileController,
    HrEmployeeController,
    LogSendOutController,
    MasterMenuController,
    SettingsController,
    TaskController,
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
Route::post('image', [Welcome::class, 'uploadImage']);

Route::middleware('auth:sanctum')->group(function () {
    //Route Group Master Menu
    Route::apiResource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);
    Route::apiResource('user_access_menu', UserAccessMenuController::class);

    //Route Group Master Users
    Route::apiResource('users', UsersController::class);
    Route::post('image_upload_employee', [HrEmployeeController::class, 'uploadImage']);
    Route::post('image_upload_personal', [DetailProfileController::class, 'uploadImage']);

    //Route Group Master Branch
    Route::apiResource('cabang', BranchController::class);

    //Route Group Master Karyawan
    Route::apiResource('karyawan', HrEmployeeController::class);

    //Route Group Master Cr Application
    Route::apiResource('cr_application', CrAppilcationController::class);
    Route::post('application_approval_kapos', [CrAppilcationController::class, 'approvalKapos']);

    // Route::get('cr_applications', [CrAppilcationController::class, 'show']);
    Route::post('cr_application_generate', [CrAppilcationController::class, 'generateUuidFPK']);
    Route::get('fpk_kapos', [CrAppilcationController::class, 'showKapos']);
    Route::get('fpk_ho', [CrAppilcationController::class, 'showHo']);

    //Route Group Master Pusher Notify
    Route::apiResource('task', TaskController::class);

    //Detail Profile
    Route::get('me', [DetailProfileController::class, 'index']);

    //Route Group Cr Prospek (Kunjungan)
    Route::apiResource('kunjungan', CrprospectController::class);
    Route::get('kunjungan_admin', [CrprospectController::class, 'showAdmins']);
    Route::post('image_upload_prospect', [CrprospectController::class, 'uploadImage']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
