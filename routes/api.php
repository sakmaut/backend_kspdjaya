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

Route::apiResource('welcome', Welcome::class);

Route::middleware('auth:sanctum')->group(function () {
    //Route Group Master Menu
    Route::apiResource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);
    Route::apiResource('user_access_menu', UserAccessMenuController::class);

    //Route Group Master Users
    Route::apiResource('users', UsersController::class);
    Route::post('image_upload_employee', [DetailProfileController::class, 'uploadImage']);

    //Route Group Master Branch
    Route::apiResource('cabang', BranchController::class);

    //Route Group Master Karyawan
    Route::apiResource('karyawan', HrEmployeeController::class);

    //Route Group Master Cr Application
    Route::apiResource('cr_application', CrAppilcationController::class);

    Route::post('cr_applications', [CrAppilcationController::class, 'show']);
    Route::post('cr_application_generate', [CrAppilcationController::class, 'generateUuidFPK']);

    //Route Group Master Pusher Notify
    Route::apiResource('task', TaskController::class);

    //Detail Profile
    Route::get('me', [DetailProfileController::class, 'index']);

    //Route Group Cr Prospek (Kunjungan)
    Route::apiResource('kunjungan', CrprospectController::class);
    Route::get('kunjungan_kapos', [CrprospectController::class, 'showKapos']);
    Route::get('kunjungan_admin', [CrprospectController::class, 'showAdmins']);
    Route::post('kunjungan_approval', [CrprospectController::class, 'approval']);
    Route::post('image_upload_prospect', [CrprospectController::class, 'uploadImage']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
