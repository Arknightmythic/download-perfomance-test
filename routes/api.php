<?php

use App\Http\Controllers\BankDataExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bank-data/export', [BankDataExportController::class, 'export']);
    Route::get('/bank-data/export/{exportJob}/status', [BankDataExportController::class, 'status']);
    Route::get('/bank-data/export/{exportJob}/download', [BankDataExportController::class, 'download'])
        ->name('export.download');
});