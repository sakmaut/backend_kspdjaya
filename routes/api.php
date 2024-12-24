<?php

use App\Http\Controllers\API\{
    AdminFeeController,
    AuthController,
    BpkbController,
    BpkbTransactionController,
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
    UsersController,
    CrBlacklistController,
    HrPositionController,
    ListBanController,
    PelunasanController,
    ReportController
};
use App\Http\Controllers\Welcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Login Authenticate
Route::post('auth/login', [AuthController::class, 'login'])->name('login');
Route::get('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('welcome', [Welcome::class, 'index']);

Route::middleware(['auth:sanctum', 'check.access'])->group(function () {
    Route::resource('users', UsersController::class)->only(['index']);
    Route::resource('cr_application', CrAppilcationController::class)->only(['index']);
    Route::resource('kunjungan', CrSurveyController::class)->only(['index']);
    Route::get('kunjungan_admin', [CrAppilcationController::class, 'showAdmins']);
   
    // Route::resource('taksasi', TaksasiController::class)->only(['index']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Route Group Master Menu
    Route::get('fpk_kapos', [CrAppilcationController::class, 'showKapos']);
    Route::get('fpk_ho', [CrAppilcationController::class, 'showHo']);
    Route::resource('menu', MasterMenuController::class);
    Route::get('menu-sub-list', [MasterMenuController::class, 'menuSubList']);
    Route::resource('user_access_menu', UserAccessMenuController::class);

    // Route Group Users
    Route::resource('users', UsersController::class)->except(['index']);
    Route::post('users_multiple', [UsersController::class, 'storeArray']);
    Route::post('image_upload_employee', [UsersController::class, 'uploadImage']);

    // Route Group Branch
    Route::resource('cabang', BranchController::class);

    // Route Group Cr Application
    Route::resource('cr_application', CrAppilcationController::class)->except(['index']);
    Route::post('application_approval_kapos', [CrAppilcationController::class, 'approvalKapos']);
    Route::post('application_approval_ho', [CrAppilcationController::class, 'approvalHo']);
    Route::post('cr_application_generate', [CrAppilcationController::class, 'generateUuidFPK']);

    // Route Group Cr Prospek (Kunjungan)
    Route::resource('kunjungan', CrSurveyController::class)->except(['index', 'howAdmins']);
    Route::post('image_upload_prospect', [CrSurveyController::class, 'uploadImage']);
    Route::post('image_upload_multiple', [CrSurveyController::class, 'imageMultiple']);
    Route::delete('image_deleted/{id}', [CrSurveyController::class, 'destroyImage']);

    // Detail Profile
    Route::get('me', [DetailProfileController::class, 'index']);
    Route::post('image_upload_personal', [DetailProfileController::class, 'multipleUpload+']);

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
    Route::post('search_customer', [CustomerController::class,'searchCustomer']);
    Route::post('check_ro', [CustomerController::class,'cekRO']);
    Route::post('kontrak_fasilitas', [CustomerController::class, 'fasilitas']);
    Route::post('struktur_kredit', [CustomerController::class, 'creditStruktur']);
    Route::resource('payment', PaymentController::class);
    Route::post('payment_attachment', [PaymentController::class, 'upload']);
    Route::post('payment_approval', [PaymentController::class, 'approval']);

    Route::post('pelunasan', [PelunasanController::class, 'checkCredit']);
    Route::post('payment_pelunasan', [PelunasanController::class, 'processPayment']);
    Route::get('list_pelunasan', [PelunasanController::class, 'index']);

    //Blacklist
    Route::resource('blacklist', CrBlacklistController::class);
    Route::post('blacklist_detail', [CrBlacklistController::class,'check']);

    Route::resource('jaminan', BpkbController::class);
    Route::resource('jaminan_transaction', BpkbTransactionController::class);
    Route::post('jaminan_approval', [BpkbTransactionController::class,'approval']);
    Route::get('jaminan_list_approval', [BpkbTransactionController::class,'listApproval']);
    Route::get('forpostjaminan', [BpkbController::class,'forpostjaminan']);
    Route::get('forgetjaminan', [BpkbController::class,'forgetjaminan']);
    Route::post('change_password', [UsersController::class,'changePassword']);
    Route::post('reset_password', [UsersController::class,'resetPassword']);

    Route::resource('position',HrPositionController::class);
    Route::post('checkCollateral', [Credit::class,'checkCollateral']);

    Route::post('arus_kas',[ListBanController::class,'index']);

    //Report
    Route::get('creditReport', [ReportController::class,'pinjaman']);
    Route::get('customerReport', [ReportController::class,'debitur']);
    Route::get('collateralReport', [ReportController::class,'jaminan']);
    Route::get('paymentReport', [ReportController::class,'pembayaran']);
    Route::get('arrearsReport', [ReportController::class,'tunggakkan']);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
