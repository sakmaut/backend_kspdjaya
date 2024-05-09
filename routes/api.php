<?php

use App\Http\Controllers\API\{
    AssetsController,
    AuthController,
    BranchController,
    CrAppilcationController,
    CreditTypeController,
    CrprospectController,
    CustomerAccountController,
    DetailProfileController,
    HrEmployeeController,
    LogSendOutController,
    LogTemporaryLinkController,
    SettingsController,
    SlikApprovalController,
    SubordinateListController,
    TaskController,
    TextFileReader,
    UsersController
};
use App\Http\Controllers\API\Menu\MasterMenuController;
use App\Http\Controllers\API\Menu\MasterRoleController;
use App\Http\Controllers\Laporan\HistoryAccController;
use App\Http\Controllers\Laporan\PaymentDumpController;
use App\Http\Controllers\Welcome;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
//ini dibuah dari luar
//Login Authenticate
Route::post('auth/login', [AuthController::class, 'login'])->name('login');
Route::get('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('payment', [PaymentDumpController::class, 'index']);
Route::post('historyAcc', [HistoryAccController::class, 'index']);

Route::get('waweb/logs', [LogSendOutController::class, 'index']);
Route::post('waweb/logs', [LogSendOutController::class, 'store']);
Route::put('waweb/logs/{id}', [LogSendOutController::class, 'update']);
Route::get('waweb/task', [LogSendOutController::class, 'filter']);

Route::get('customerAccount', [CustomerAccountController::class, 'index']);

Route::post('createurl', [SlikApprovalController::class, 'creaturl']);
//? check incoming url signed

Route::get('getExpiredLink/{id}', [LogTemporaryLinkController::class, 'index']);

// return cr_prospect data
Route::get('kunjungan/detailApproval/{id}', [CrprospectController::class, 'detailApproval'])
    ->name('approve_slik')
    ->middleware('signed');

//! update approval by action user
Route::put('approval', [SlikApprovalController::class, 'approveCustomer']);

Route::put('editusers', [UsersController::class, 'update']);

Route::post('createUser', [UsersController::class, 'store']);

Route::get('approval/{id}', [SlikApprovalController::class, 'index']);

Route::post('text_file', [TextFileReader::class, 'uploadText']);

Route::apiResource('welcome', Welcome::class);

Route::middleware('auth:sanctum')->group(function () {
    //Route Group Master Menu
    Route::apiResource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);

    //Route Group Master Users
    Route::apiResource('users', UsersController::class);
    Route::post('image_upload_employee', [UsersController::class, 'uploadImage']);

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

    //Route Group Credit Type
    Route::get('credit_type/{status}', [CreditTypeController::class, 'index']);
    Route::get('credit_type', [CreditTypeController::class, 'index']);

    //Route Group Cr Prospek (Kunjungan)
    Route::get('kunjungan', [CrprospectController::class, 'index']);
    Route::get('kunjungan/{id}', [CrprospectController::class, 'detail']);
    Route::post('kunjungan', [CrprospectController::class, 'store']);
    Route::put('kunjungan/{id}', [CrprospectController::class, 'update']);
    Route::delete('kunjungan/{id}', [CrprospectController::class, 'destroy']);
    Route::post('image_upload_prospect', [CrprospectController::class, 'uploadImage']);
    Route::post('multi-upload-images', [CrprospectController::class, 'multiImage']);

    Route::post('getSpv', [SubordinateListController::class, 'spvSearch']);
    Route::post('getHierarchy', [SubordinateListController::class, 'getHierarchy']);

    Route::put('slikSpv', [SlikApprovalController::class, 'approveSpv']);

    // Route::post('text_file', [TextFileReader::class, 'uploadText']);
});

Route::post('assets', [AssetsController::class, 'storeAsset']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
