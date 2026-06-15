<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CloneController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/sync-logs', [DashboardController::class, 'syncLogs'])->name('dashboard.sync-logs');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects/{project}/git', [ProjectController::class, 'gitAction'])->name('projects.git');

    Route::post('/clone', [CloneController::class, 'store'])->name('clone.store');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/password', [SettingsController::class, 'changePassword'])->name('settings.password');
});

require __DIR__.'/auth.php';
