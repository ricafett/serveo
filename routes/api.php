<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingDocumentController;
use App\Http\Controllers\Api\BillingGroupController;
use App\Http\Controllers\Api\EventLogController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\OccupiedZoneController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductionTicketController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\Admin\AccountingExportController;
use App\Http\Controllers\Api\Admin\BillingStatusController;
use App\Http\Controllers\Api\Admin\MenuCategoryController;
use App\Http\Controllers\Api\Admin\MenuItemController;
use App\Http\Controllers\Api\Admin\PrinterController;
use App\Http\Controllers\Api\Admin\PrinterRouteController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\RowController;
use App\Http\Controllers\Api\Admin\SeatController;
use App\Http\Controllers\Api\Admin\SeatPairController;
use App\Http\Controllers\Api\Admin\SectionController;
use App\Http\Controllers\Api\Admin\TranslationController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VenueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Authentication
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware('api.auth')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    // Floor & Occupancy (requires auth)
    Route::middleware('api.auth')->group(function () {
        Route::get('sessions/current', [SessionController::class, 'current']);
        Route::get('floor', [FloorController::class, 'index']);

        // Billing Groups
        Route::post('billing-groups', [BillingGroupController::class, 'store'])
            ->middleware('permission:floor.open_billing_group');
        Route::get('billing-groups/{billingGroup}', [BillingGroupController::class, 'show'])
            ->middleware('permission:billing_group.view');
        Route::patch('billing-groups/{billingGroup}', [BillingGroupController::class, 'update'])
            ->middleware('permission:billing_group.set_status');
        Route::post('billing-groups/{billingGroup}/zones', [BillingGroupController::class, 'storeZones'])
            ->middleware('permission:floor.assign_zone');
        Route::get('billing-groups/{billingGroup}/orders', [BillingGroupController::class, 'orders'])
            ->middleware('permission:billing_group.view');
        Route::get('billing-groups/{billingGroup}/production-tickets', [BillingGroupController::class, 'productionTickets'])
            ->middleware('permission:production_ticket.view');
        Route::get('billing-groups/{billingGroup}/bill-summary', [BillingGroupController::class, 'billSummary'])
            ->middleware('permission:billing_group.view');
        Route::post('billing-groups/{billingGroup}/reopen', [BillingGroupController::class, 'reopen'])
            ->middleware('permission:billing_group.reopen');

        // Occupied Zones
        Route::patch('occupied-zones/{occupiedZone}', [OccupiedZoneController::class, 'update'])
            ->middleware('permission:floor.release_zone');
        Route::get('occupied-zones/{occupiedZone}', [OccupiedZoneController::class, 'show'])
            ->middleware('permission:floor.view');

        // Orders
        Route::post('orders', [OrderController::class, 'store'])
            ->middleware('permission:order.create');
        Route::get('orders/{orderHeader}', [OrderController::class, 'show'])
            ->middleware('permission:order.create');
        Route::post('orders/{orderHeader}/void-items', [OrderController::class, 'voidItems'])
            ->middleware('permission:order.void_item');

        // Production Tickets
        Route::get('production-tickets/{productionTicket}', [ProductionTicketController::class, 'show'])
            ->middleware('permission:production_ticket.view');
        Route::post('production-tickets/{productionTicket}/reprint', [ProductionTicketController::class, 'reprint'])
            ->middleware('permission:production_ticket.reprint');

        // Billing Documents
        Route::post('billing-documents', [BillingDocumentController::class, 'store'])
            ->middleware('permission:billing_document.create');
        Route::post('billing-documents/{billingDocument}/reprint', [BillingDocumentController::class, 'reprint'])
            ->middleware('permission:billing_document.reprint');

        // Payments
        Route::post('payments', [PaymentController::class, 'store'])
            ->middleware('permission:payment.record');

        // Event Log
        Route::get('event-log', [EventLogController::class, 'index'])
            ->middleware('permission:audit.view');
        Route::get('event-log/{auditEvent}', [EventLogController::class, 'show'])
            ->middleware('permission:audit.view');
    });

    // Admin Configuration (requires auth + admin permissions)
    Route::middleware(['api.auth', 'role:ADMIN'])->prefix('admin')->group(function () {
        // Venues
        Route::get('venues/{venue}', [VenueController::class, 'show']);
        Route::get('venues/{venue}/layout', [VenueController::class, 'layout']);

        // Sections
        Route::post('sections', [SectionController::class, 'store']);
        Route::patch('sections/{section}', [SectionController::class, 'update']);

        // Rows
        Route::post('rows', [RowController::class, 'store']);
        Route::patch('rows/{row}', [RowController::class, 'update']);

        // Seats
        Route::post('seats', [SeatController::class, 'store']);
        Route::patch('seats/{seat}', [SeatController::class, 'update']);

        // Seat Pairs
        Route::post('seat-pairs', [SeatPairController::class, 'store']);
        Route::patch('seat-pairs/{seatPair}', [SeatPairController::class, 'update']);

        // Billing Statuses
        Route::get('billing-statuses', [BillingStatusController::class, 'index']);
        Route::post('billing-statuses', [BillingStatusController::class, 'store']);
        Route::patch('billing-statuses/{billingStatus}', [BillingStatusController::class, 'update']);

        // Menu Categories
        Route::get('menu-categories', [MenuCategoryController::class, 'index']);
        Route::post('menu-categories', [MenuCategoryController::class, 'store']);

        // Menu Items
        Route::get('menu-items', [MenuItemController::class, 'index']);
        Route::post('menu-items', [MenuItemController::class, 'store']);
        Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update']);

        // Printers
        Route::get('printers', [PrinterController::class, 'index']);
        Route::post('printers', [PrinterController::class, 'store']);
        Route::patch('printers/{printer}', [PrinterController::class, 'update']);

        // Printer Routes
        Route::get('printer-routes', [PrinterRouteController::class, 'index']);
        Route::post('printer-routes', [PrinterRouteController::class, 'store']);
        Route::patch('printer-routes/{printerRoute}', [PrinterRouteController::class, 'update']);

        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);

        // Roles
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('users/{user}/roles', [RoleController::class, 'assign']);
        Route::patch('users/{user}/roles/{role}', [RoleController::class, 'updateAssignment']);

        // Translations
        Route::get('translations', [TranslationController::class, 'index']);

        // Accounting Exports
        Route::get('accounting-exports', [AccountingExportController::class, 'index']);
        Route::post('accounting-exports', [AccountingExportController::class, 'store']);
        Route::get('accounting-exports/{accountingExport}', [AccountingExportController::class, 'show']);
        Route::get('accounting-exports/{accountingExport}/download', [AccountingExportController::class, 'download']);
    });
});
