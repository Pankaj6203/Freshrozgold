<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\GoldController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AugmontUserController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AugmontKycController;
use App\Http\Controllers\Api\FdController;
use App\Http\Controllers\Api\SipController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\RateController;
use App\Http\Controllers\Api\WithdrawController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// =============================================
// AUTH APIs (Public) — rate-limited
// =============================================
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('/merchant-login', [AuthController::class, 'merchantLogin']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/send-email-otp', [AuthController::class, 'sendEmailOtp']);
    Route::post('/verify-email-otp', [AuthController::class, 'verifyEmailOtp']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// OTP APIs (Public) — rate-limited
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/send-otp', [OtpController::class, 'sendOtp']);
    Route::post('/resend-otp', [OtpController::class, 'resendOtp']);
    Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);
});

// =============================================
// PUBLIC APIs (no auth required)
// =============================================

// Master Data
Route::prefix('master')->group(function () {
    Route::get('/states', [MasterController::class, 'states']);
    Route::get('/cities/{stateId}', [MasterController::class, 'cities']);
    Route::get('/banks', [MasterController::class, 'banks']);
});

// Rates (public)
Route::prefix('rates')->group(function () {
    Route::get('/live', [RateController::class, 'live']);
    Route::get('/historical', [RateController::class, 'historical']);
});

// Products (public)
Route::prefix('augmont/products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{productId}', [ProductController::class, 'show']);
});

// FD schemes (public, no user-context needed)
Route::get('/augmont/fd/schemes', [FdController::class, 'schemes']);

// FD terms moved to protected routes (requires uniqueId)
// Route::get('/augmont/fd/terms') is now inside auth:sanctum group

// SIP rates (public)
Route::get('/augmont/sip/rates', [SipController::class, 'rates']);

// =============================================
// WEBHOOKS (no auth, verified by payload/IP)
// =============================================
Route::prefix('webhooks')->group(function () {
    Route::post('/quickekyc-status', [WebhookController::class, 'quickeKycStatus']);
    Route::post('/augmont/user-create', [WebhookController::class, 'augmontUserCreate']);
    Route::post('/augmont/fd-monthly-interest', [WebhookController::class, 'fdMonthlyInterest']);
    Route::post('/augmont/fd-matured', [WebhookController::class, 'fdMatured']);
    Route::post('/augmont/fd-closed', [WebhookController::class, 'fdClosed']);
    Route::post('/cashfree', [WebhookController::class, 'cashfreePayment']);
    Route::post('/transaction-status', [WebhookController::class, 'transactionStatus']);
});

// =============================================
// PROTECTED APIs (auth:sanctum required)
// =============================================
Route::middleware('auth:sanctum')->group(function () {

    // User Profile & Dashboard
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::post('/profile', [UserController::class, 'updateProfile']);
        Route::get('/dashboard', [UserController::class, 'dashboard']);
        Route::get('/transactions', [UserController::class, 'transactions']);
    });

    // KYC APIs
    Route::prefix('kyc')->group(function () {
        Route::post('/verify-pan', [KycController::class, 'verifyPan']);
        Route::post('/status', [KycController::class, 'getKycStatus']);
        Route::post('/aadhaar/generate-otp', [KycController::class, 'aadhaarGenerateOtp']);
        Route::post('/aadhaar/verify-otp', [KycController::class, 'aadhaarVerifyOtp']);
        Route::post('/submit', [KycController::class, 'submitKyc']);
    });

    // Payment APIs
    Route::prefix('payment')->group(function () {
        Route::post('/create-order', [PaymentController::class, 'createOrder']);
        Route::get('/order-status', [PaymentController::class, 'getOrderStatus']);
    });

    // Wallet APIs
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::post('/add-money', [WalletController::class, 'addMoney']);
        Route::post('/verify-payment', [WalletController::class, 'verifyPayment']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });

    // Gold APIs
    Route::prefix('gold')->group(function () {
        Route::get('/rates', [GoldController::class, 'getRates']);
        Route::post('/buy', [GoldController::class, 'buyGold']);
        Route::post('/sell', [GoldController::class, 'sellGold']);
        Route::post('/buy-invoice', [GoldController::class, 'getBuyInvoice']);
        Route::post('/sell-invoice', [GoldController::class, 'getSellInvoice']);
        Route::get('/buy-info', [GoldController::class, 'getBuyInfo']);
        Route::get('/sell-info', [GoldController::class, 'getSellInfo']);
        Route::get('/buy-list', [GoldController::class, 'getBuyList']);
        Route::get('/sell-list', [GoldController::class, 'getSellList']);
        Route::post('/update-transaction-status', [GoldController::class, 'updateTransactionStatus']);
    });

    // Local Bank CRUD (without Augmont)
    Route::prefix('banks')->group(function () {
        Route::get('/', [BankController::class, 'listLocal']);
        Route::post('/add', [BankController::class, 'addLocal']);
        Route::post('/verify', [BankController::class, 'verifyBankAccount']);
        Route::put('/{id}', [BankController::class, 'updateLocal']);
        Route::delete('/{id}', [BankController::class, 'deleteLocal']);
    });

    // =============================================
    // AUGMONT APIs (new migration routes)
    // All routes with {uniqueId} are ownership-checked.
    // =============================================

    // Augmont User Management
    Route::prefix('augmont/users')->group(function () {
        Route::post('/', [AugmontUserController::class, 'store']);
        Route::put('/{uniqueId}', [AugmontUserController::class, 'update'])->middleware('augmont.owner');
        Route::get('/{uniqueId}/passbook', [AugmontUserController::class, 'passbook'])->middleware('augmont.owner');
    });

    // Augmont Bank Management
    Route::prefix('augmont/banks')->middleware('augmont.owner')->group(function () {
        Route::post('/', [BankController::class, 'store']);
        Route::get('/{uniqueId}', [BankController::class, 'index']);
        Route::delete('/{uniqueId}/{bankId}', [BankController::class, 'destroy']);
        Route::get('/validate-ifsc/{ifsc}', [BankController::class, 'validateIfsc'])->withoutMiddleware('augmont.owner');
    });

    // Augmont Address Management
    Route::prefix('augmont/addresses')->middleware('augmont.owner')->group(function () {
        Route::post('/', [AddressController::class, 'store']);
        Route::get('/{uniqueId}', [AddressController::class, 'index']);
        Route::delete('/{uniqueId}/{addressId}', [AddressController::class, 'destroy']);
    });

    // Augmont KYC (separate from local KYC)
    Route::prefix('augmont/kyc')->middleware('augmont.owner')->group(function () {
        Route::get('/{uniqueId}', [AugmontKycController::class, 'show']);
        Route::put('/{uniqueId}', [AugmontKycController::class, 'update']);
    });

    // Augmont FD (Fixed Deposit)
    Route::prefix('augmont/fd')->middleware('augmont.owner')->group(function () {
        Route::get('/terms', [FdController::class, 'terms']);
        Route::post('/', [FdController::class, 'store']);
        Route::post('/pre-order', [FdController::class, 'preOrder']);
        Route::get('/{uniqueId}', [FdController::class, 'index']);
        Route::get('/{uniqueId}/transactions', [FdController::class, 'transactions']);
        Route::get('/{uniqueId}/{fdId}', [FdController::class, 'show']);
        Route::post('/{uniqueId}/{fdId}/close', [FdController::class, 'close']);
        Route::get('/{uniqueId}/{fdId}/passbook', [FdController::class, 'passbook']);
        Route::post('/{uniqueId}/{fdId}/pre-close-calc', [FdController::class, 'preCloseCalc']);
    });

    // Augmont SIP
    Route::prefix('augmont/sip')->group(function () {
        Route::get('/', [SipController::class, 'index']);
        Route::post('/', [SipController::class, 'store']);
        Route::put('/{id}', [SipController::class, 'update']);
    });

    // Augmont Transfers
    Route::prefix('augmont/transfers')->middleware('augmont.owner')->group(function () {
        Route::post('/', [TransferController::class, 'store']);
        Route::get('/{uniqueId}', [TransferController::class, 'index']);
        Route::get('/{uniqueId}/{transferId}', [TransferController::class, 'show']);
    });

    // Augmont Orders (physical product redemption)
    Route::prefix('augmont/orders')->middleware('augmont.owner')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{uniqueId}', [OrderController::class, 'index']);
        Route::get('/{uniqueId}/{orderId}', [OrderController::class, 'show']);
    });

    // Augmont Invoices
    Route::prefix('augmont/invoices')->group(function () {
        Route::get('/buy/{transactionId}', [InvoiceController::class, 'buy']);
        Route::get('/sell/{transactionId}', [InvoiceController::class, 'sell']);
        Route::get('/redeem/{transactionId}', [InvoiceController::class, 'redeem']);
    });

    // Augmont Withdraw
    Route::prefix('augmont/withdraw')->middleware('augmont.owner')->group(function () {
        Route::get('/{uniqueId}', [WithdrawController::class, 'info']);
        Route::put('/', [WithdrawController::class, 'update']);
    });

    // Augmont Balance
    Route::get('/augmont/balance/{uniqueId}', [WithdrawController::class, 'balance'])->middleware('augmont.owner');
});

