<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\MonitorLabelController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::prefix('/configuration')->group(function () {
        Route::get('/', [ConfigurationController::class, 'index'])->name('configuration.index');
        Route::post('/windows-domains', [ConfigurationController::class, 'store'])->name('configuration.domains.store');
        Route::put('/windows-domains/{domain}', [ConfigurationController::class, 'update'])->name('configuration.domains.update');
        Route::delete('/windows-domains/{domain}', [ConfigurationController::class, 'destroy'])->name('configuration.domains.destroy');
        Route::post('/windows-domains/{domain}/sync', [ConfigurationController::class, 'sync'])->name('configuration.domains.sync');
    });

    Route::prefix('/monitors')->group(function () {
        Route::get('new', [MonitorController::class, 'new'])->name('monitors.new');
        Route::post('new', [MonitorController::class, 'create'])->name('monitors.create');
        Route::get('{monitor}/edit', [MonitorController::class, 'edit'])->name('monitors.edit');
        Route::put('{monitor}', [MonitorController::class, 'update'])->name('monitors.update');
        Route::post('delete', [MonitorController::class, 'delete'])->name('monitors.delete');
        Route::post('{monitor}/refresh', [MonitorController::class, 'refresh'])->name('monitors.refresh');
        Route::post('{monitor}/labels', [MonitorLabelController::class, 'store'])->name('monitors.labels.store');
        Route::delete('{monitor}/labels/{label}', [MonitorLabelController::class, 'destroy'])->name('monitors.labels.destroy');
        Route::get('run-ondemand', [MonitorController::class, 'runMonitorsOnDemand'])->name('monitors.run-ondemand');
    });

    // Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    // Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
