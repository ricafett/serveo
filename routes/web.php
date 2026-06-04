<?php

use App\Http\Controllers\Web\AuthController;
use App\Livewire\BillingGroup\BillingGroupDetail;
use App\Livewire\BillingGroup\BillingGroupLookup;
use App\Livewire\Cashier\ReprintPanel;
use App\Livewire\Cashier\SalesIndex;
use App\Livewire\Floor\FloorIndex;
use App\Livewire\Home\Dashboard;
use App\Livewire\Menu\MenuIndex;
use App\Livewire\Order\OrderEntry;
use App\Models\AccountingExport;
use App\Models\Backup;
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
    Route::get('/home', Dashboard::class)->name('home');

    // Server routes
    Route::middleware('role:SERVER,CASHIER,ADMIN')->group(function () {
        Route::get('/floor', FloorIndex::class)->name('floor');
        Route::get('/orders/new/{billingGroupId}', OrderEntry::class)->name('orders.new');
    });

    // Shared billing-group detail (server + cashier + admin)
    Route::middleware('role:SERVER,CASHIER,ADMIN')->group(function () {
        Route::get('/billing-groups/{id}', BillingGroupDetail::class)->name('billing-groups.detail');
    });

    // Read-only menu catalog (all interactive roles)
    Route::middleware('role:SERVER,CASHIER,ADMIN')->group(function () {
        Route::get('/menu', MenuIndex::class)->name('menu');
    });

    // Cashier routes
    Route::middleware('role:CASHIER,ADMIN')->group(function () {
        Route::get('/lookup', BillingGroupLookup::class)->name('lookup');
        Route::get('/reprint/{billingGroupId}', ReprintPanel::class)->name('reprint.group');
        Route::get('/sales', SalesIndex::class)->name('sales');
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

// Backup download (protected)
Route::get('/backups/{backup}/download', function (Backup $backup) {
    if ($backup->backup_status !== 'COMPLETED' || ! $backup->file_name) {
        abort(404);
    }

    if (! Storage::disk('local')->exists($backup->file_name)) {
        abort(404);
    }

    return Storage::disk('local')->download($backup->file_name, basename($backup->file_name), [
        'Content-Type' => 'application/octet-stream',
    ]);
})->name('backup.download')->middleware('auth');
