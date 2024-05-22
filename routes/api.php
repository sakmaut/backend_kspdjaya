<?php

use App\Http\Controllers\API\{
    AssetsController,
    AuthController,
    BranchController,
    CrAppilcationController,
    CrprospectController,
    DetailProfileController,
    HrEmployeeController,
    LogSendOutController,
    LogTemporaryLinkController,
    MasterMenuController,
    SettingsController,
    TaskController,
    UsersController
};

use App\Http\Controllers\Welcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Login Authenticate
Route::post('auth/login', [AuthController::class, 'login'])->name('login');
Route::get('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('waweb/logs', [LogSendOutController::class, 'index']);
Route::post('waweb/logs', [LogSendOutController::class, 'store']);
Route::put('waweb/logs/{id}', [LogSendOutController::class, 'update']);
Route::get('waweb/task', [LogSendOutController::class, 'filter']);

Route::get('getExpiredLink/{id}', [LogTemporaryLinkController::class, 'index']);

// return cr_prospect data
Route::get('kunjungan/detailApproval/{id}', [CrprospectController::class, 'detailApproval'])
    ->name('approve_slik')
    ->middleware('signed');

Route::put('editusers', [UsersController::class, 'update']);

Route::post('createUser', [UsersController::class, 'store']);

Route::apiResource('welcome', Welcome::class);

Route::middleware('auth:sanctum')->group(function () {
    //Route Group Master Menu
    Route::apiResource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);

    //Route Group Master Users
    Route::apiResource('users', UsersController::class);
    Route::post('image_upload_employee', [DetailProfileController::class, 'uploadImage']);

    //Route Group Master Users
    Route::apiResource('settings', SettingsController::class);

    //Route Group Master Branch
    Route::apiResource('cabang', BranchController::class);

    //Route Group Master Karyawan
    Route::apiResource('karyawan', HrEmployeeController::class);

    //Route Group Master Cr Application
    Route::apiResource('cr_application', CrAppilcationController::class);

    //Route Group Master Pusher Notify
    Route::apiResource('task', TaskController::class);

    //Detail Profile
    Route::get('me', [DetailProfileController::class, 'index']);

    //Route Group Cr Prospek (Kunjungan)
    Route::apiResource('kunjungan', CrprospectController::class);
    Route::get('kunjungan_kapos', [CrprospectController::class, 'showKapos']);
    Route::post('image_upload_prospect', [CrprospectController::class, 'uploadImage']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
