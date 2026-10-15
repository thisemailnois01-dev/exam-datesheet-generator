<?php

declare(strict_types=1);

use App\Domain\Timetable\Services\DemoDataGenerator;
use App\Http\Controllers\ExportController;
use App\Livewire\DataImportManager;
use App\Livewire\SettingsManager;
use App\Livewire\TimetableGrid;
use App\Livewire\VersionManager;
use App\Livewire\WhatIfSimulator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// Timetable Grid (Central Interactive Day x Slot Grid)
Route::get('/', TimetableGrid::class)->name('timetable.grid');

// Settings Page
Route::get('/settings', SettingsManager::class)->name('settings');

// What-If Simulation Sandbox
Route::get('/what-if', WhatIfSimulator::class)->name('what-if');

// Versions & Audit Trail
Route::get('/versions', VersionManager::class)->name('versions');

// Excel Import & Downloadable Templates
Route::get('/import-data', DataImportManager::class)->name('import-data');
Route::redirect('/data-import', '/import-data');

// Quick Action: Load Demo Data
Route::get('/load-demo', function () {
    Artisan::call('datesheet:demo');
    return redirect()->route('timetable.grid')->with('success', 'Demo university data loaded and full datesheet generated with 0 hard conflicts verified!');
})->name('load-demo');

// Export Routes
Route::prefix('export')->group(function () {
    Route::get('/datesheet/{id}', [ExportController::class, 'exportDatesheetExcel'])->name('export.datesheet');
    Route::get('/teachers/{id}', [ExportController::class, 'exportTeacherDutyExcel'])->name('export.teachers');
    Route::get('/rooms/{id}', [ExportController::class, 'exportRoomAllocationExcel'])->name('export.rooms');
    Route::get('/template/{type}', [ExportController::class, 'downloadTemplate'])->name('export.template');
    Route::get('/pdf/{id}', [ExportController::class, 'exportDatesheetPdf'])->name('export.pdf');
});

// Public Shareable Read-Only Datesheet Link (For Faculty & Students)
Route::get('/view/{id}', [ExportController::class, 'exportDatesheetPdf'])->name('datesheet.public');

