<?php

use App\Http\Controllers\Operational\HomeController;
use App\Http\Controllers\Web\AuthController;
use App\Models\AccountingExport;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Public routes
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home');
    }

    return redirect()->route('login');
});

// Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Operational UI — requires authentication
Route::middleware('auth')->group(function () {
    // Role-based dashboard homepage
    Route::get('/home', \App\Livewire\Home\Dashboard::class)->name('home');

    // Server routes
    Route::middleware('role:SERVER,ADMIN')->group(function () {
        Route::get('/floor', \App\Livewire\Floor\FloorIndex::class)->name('floor');
        Route::get('/orders/new/{billingGroupId}', \App\Livewire\Order\OrderEntry::class)->name('orders.new');
    });

    // Shared billing-group detail (server + cashier + admin)
    Route::middleware('role:SERVER,CASHIER,ADMIN')->group(function () {
        Route::get('/billing-groups/{id}', \App\Livewire\BillingGroup\BillingGroupDetail::class)->name('billing-groups.detail');
    });

    // Cashier routes
    Route::middleware('role:CASHIER,ADMIN')->group(function () {
        Route::get('/lookup', \App\Livewire\BillingGroup\BillingGroupLookup::class)->name('lookup');
        Route::get('/checkout/{id}', \App\Livewire\Cashier\Checkout::class)->name('checkout');
        Route::get('/reprint', \App\Livewire\Cashier\ReprintPanel::class)->name('reprint');
        Route::get('/reprint/{billingGroupId}', \App\Livewire\Cashier\ReprintPanel::class)->name('reprint.group');
    });
});

// Accounting export download (protected)
Route::get('/accounting-exports/{export}/download', function (AccountingExport $export) {
    if ($export->export_status !== 'COMPLETED' || ! $export->file_name) {
        abort(404);
    }

    if (! Storage::disk('local')->exists($export->file_name)) {
        abort(404);
    }

    return Storage::disk('local')->download($export->file_name, basename($export->file_name), [
        'Content-Type' => 'text/csv',
    ]);
})->name('accounting-export.download')->middleware('auth');
