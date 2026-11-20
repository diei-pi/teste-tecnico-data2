<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReportController::class, 'index'])->name('dashboard');
Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
Route::get('/reports/{report}/status', [ReportController::class, 'status'])->name('reports.status');
