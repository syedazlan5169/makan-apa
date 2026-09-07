<?php

use App\Http\Controllers\AdminAuthenticatedSessionController;
use App\Http\Controllers\AdminMenuSubmissionController;
use App\Http\Controllers\MakanController;
use App\Http\Controllers\MenuSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MakanController::class, 'index'])
    ->name('makan.index');

Route::post('/pick', [MakanController::class, 'pick'])
    ->name('makan.pick');

Route::post('/accept', [MakanController::class, 'accept'])
    ->name('makan.accept');

Route::post('/switch', [MakanController::class, 'switchUser'])
    ->name('makan.switch');

Route::get('/suggest-menu', [MenuSubmissionController::class, 'create'])
    ->name('menu-submissions.create');

Route::post('/suggest-menu', [MenuSubmissionController::class, 'store'])
    ->middleware('throttle:menu-submissions')
    ->name('menu-submissions.store');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthenticatedSessionController::class, 'create'])
        ->name('admin.login');

    Route::post('/admin/login', [AdminAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:admin-login')
        ->name('admin.login.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/menu-submissions', [AdminMenuSubmissionController::class, 'index'])
        ->name('menu-submissions.index');

    Route::patch('/menu-submissions/{menuSubmission}/approve', [AdminMenuSubmissionController::class, 'approve'])
        ->name('menu-submissions.approve');

    Route::patch('/menu-submissions/{menuSubmission}/reject', [AdminMenuSubmissionController::class, 'reject'])
        ->name('menu-submissions.reject');
});