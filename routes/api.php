<?php

use App\Http\Controllers\API\{
    AdminFeeController,
    AuthController,
    BranchController,
    CrAppilcationController,
    Credit,
    CrSurveyController,
    CustomerController,
    DetailProfileController,
    MasterMenuController,
    PaymentController,
    TaksasiController,
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
    // Route::resource('taksasi', TaksasiController::class)->only(['index']);
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
    Route::post('pk', [Credit::class, 'index']);
    Route::resource('admin_fee', AdminFeeController::class);
    Route::post('fee_survey', [AdminFeeController::class, 'fee_survey']);
    Route::post('fee', [AdminFeeController::class, 'fee']);

    // Route::resource('taksasi', TaksasiController::class)->except(['index']);
    Route::resource('taksasi', TaksasiController::class);
    Route::get('taksasi_brand', [TaksasiController::class, 'brandList']);
    Route::post('taksasi_code_model', [TaksasiController::class, 'codeModelList']);
    Route::post('taksasi_year', [TaksasiController::class, 'year']);
    Route::post('taksasi_price', [TaksasiController::class, 'price']);


    Route::resource('customer', CustomerController::class);
    Route::post('check_ro', [CustomerController::class,'cekRO']);
    Route::post('kontrak_fasilitas', [CustomerController::class, 'fasilitas']);
    Route::post('struktur_kredit', [CustomerController::class, 'creditStruktur']);
    Route::resource('payment', PaymentController::class);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
