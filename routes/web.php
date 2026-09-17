<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GymClassController;
use App\Http\Controllers\IclockController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipPlanController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ZktecoDeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Fit Generation Gym Management
|--------------------------------------------------------------------------
*/

/* ZKTeco K50 ADMS push — no auth (device HTTP client) */
Route::prefix('iclock')->group(function () {
    Route::match(['get', 'post'], 'cdata', [IclockController::class, 'cdata']);
    Route::match(['get', 'post'], 'getrequest', [IclockController::class, 'getrequest']);
    Route::match(['get', 'post'], 'devicecmd', [IclockController::class, 'deviceCmd']);
    Route::match(['get', 'post'], 'test', [IclockController::class, 'test']);
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('sw.js', function () {
    $path = public_path('sw.js');
    abort_unless(is_file($path), 404);

    $scope = rtrim(parse_url((string) config('app.url'), PHP_URL_PATH) ?: '/', '/') . '/';

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => $scope,
        'Cache-Control' => 'no-cache',
    ]);
})->name('webpush.sw');

Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    /* Management */
    Route::get('members/export-pending-fees', [MemberController::class, 'exportPendingFees'])->name('members.export-pending-fees');
    Route::get('members/pending-fees', [MemberController::class, 'pendingFees'])->name('members.pending-fees');
    Route::resource('members', MemberController::class);
    Route::resource('trainers', TrainerController::class);
    Route::resource('classes', GymClassController::class)->parameters(['classes' => 'gym_class']);
    Route::resource('plans', MembershipPlanController::class);

    /* Finance — fee collection only (invoices removed from product surface) */
    Route::get('payments/member-context/{member}', [PaymentController::class, 'memberContext'])
        ->name('payments.member-context');
    Route::resource('payments', PaymentController::class);
    Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::post('expenses/categories', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
    Route::put('expenses/categories/{expenseCategory}', [ExpenseController::class, 'updateCategory'])->name('expenses.categories.update');
    Route::delete('expenses/categories/{expenseCategory}', [ExpenseController::class, 'destroyCategory'])->name('expenses.categories.destroy');
    Route::resource('expenses', ExpenseController::class);
    Route::resource('accounts', AccountController::class);

    /* Operations */
    Route::resource('attendance', AttendanceController::class);
    Route::get('biometric-device', [ZktecoDeviceController::class, 'index'])->name('zkteco.index');
    Route::post('biometric-device/connect', [ZktecoDeviceController::class, 'connect'])->name('zkteco.connect');
    Route::post('biometric-device/live-sync', [ZktecoDeviceController::class, 'liveSync'])->name('zkteco.live-sync');
    Route::get('biometric-device/status', [ZktecoDeviceController::class, 'deviceStatus'])->name('zkteco.status');
    Route::post('biometric-device/{device}/sync', [ZktecoDeviceController::class, 'sync'])->name('zkteco.sync');
    Route::get('biometric-device/welcome', [ZktecoDeviceController::class, 'welcome'])->name('zkteco.welcome');
    Route::get('biometric-device/poll-checkin', [ZktecoDeviceController::class, 'pollCheckin'])->name('zkteco.poll-checkin');
    Route::put('biometric-device/{device}', [ZktecoDeviceController::class, 'update'])->name('zkteco.update');
    Route::delete('biometric-device/{device}', [ZktecoDeviceController::class, 'destroy'])->name('zkteco.destroy');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    /* Settings */
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
});
