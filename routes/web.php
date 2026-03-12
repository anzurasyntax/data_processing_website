<?php

use App\Http\Controllers\FileProcessingController;
use App\Http\Controllers\UploadedFileController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('auth.login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login.submit');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.register');
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [UploadedFileController::class, 'dashboard'])->name('dashboard');

    // Upload page (also shows user's uploaded files)
    Route::get('/files', [UploadedFileController::class, 'index'])->name('files.upload');
    Route::post('/files', [UploadedFileController::class, 'store'])->name('files.store');
    Route::delete('/files/{slug}', [UploadedFileController::class, 'destroy'])->name('files.delete');
    Route::get('/files/{slug}/quality', [UploadedFileController::class, 'quality'])->name('files.quality');
    Route::get('/files/{slug}/status', [UploadedFileController::class, 'status'])->name('files.status');

    // Listing + preview/processing
    Route::get('/my-files', [FileProcessingController::class, 'index'])->name('files.list');
    Route::get('/my-files/{slug}', [FileProcessingController::class, 'show'])->name('files.preview');

    // File operations (slug-based)
    Route::put('/files/{slug}/cell', [FileProcessingController::class, 'updateCell'])->name('files.cell.update');
    Route::post('/files/{slug}/clean', [FileProcessingController::class, 'cleanData'])->name('files.clean');
    Route::get('/files/{slug}/quality-check', [FileProcessingController::class, 'qualityCheck'])->name('files.quality-check');
    Route::get('/files/{slug}/visualize', [FileProcessingController::class, 'visualize'])->name('files.visualize');
    Route::get('/files/{slug}/visualize-data', [FileProcessingController::class, 'visualizeData'])->name('files.visualize-data');
    Route::get('/files/{slug}/visualize-suggestions', [FileProcessingController::class, 'visualizeSuggestions'])->name('files.visualize-suggestions');
    Route::post('/files/{slug}/visualize-build', [FileProcessingController::class, 'visualizeBuild'])->name('files.visualize-build');
    Route::get('/files/{slug}/insight-strategy', [FileProcessingController::class, 'insightStrategy'])->name('files.insight-strategy');
    Route::get('/files/{slug}/insight-strategy-data', [FileProcessingController::class, 'insightStrategyData'])->name('files.insight-strategy-data');
    Route::get('/files/{slug}/versions', [FileProcessingController::class, 'versions'])->name('files.versions');
    Route::post('/files/{slug}/revert/{version}', [FileProcessingController::class, 'revert'])->name('files.revert');
    Route::get('/files/{slug}/export/{version}/{format}', [FileProcessingController::class, 'export'])->name('files.export');
});
