<?php

use App\Models\AccountingExport;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

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
