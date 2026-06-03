<?php

declare(strict_types=1);

use App\Http\Controllers\AssignmentSuggestionActionController;
use App\Http\Controllers\AssignmentSuggestionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GlpiAiAuditController;
use App\Http\Controllers\GlpiAiDashboardController;
use App\Http\Controllers\GlpiAiSettingsController;
use App\Http\Controllers\ManualAnalysisController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
});

Route::middleware(['web', 'auth'])->prefix('glpi-ai')->name('glpi-ai.')->group(function (): void {
    Route::get('/', GlpiAiDashboardController::class)->name('dashboard');
    Route::get('/suggestions', [AssignmentSuggestionController::class, 'index'])->name('suggestions.index');
    Route::get('/suggestions/{suggestion}', [AssignmentSuggestionController::class, 'show'])->name('suggestions.show');
    Route::post('/suggestions/{suggestion}/approve', [AssignmentSuggestionActionController::class, 'approve'])->name('suggestions.approve');
    Route::post('/suggestions/{suggestion}/reject', [AssignmentSuggestionActionController::class, 'reject'])->name('suggestions.reject');
    Route::post('/suggestions/{suggestion}/assign-technician', [AssignmentSuggestionActionController::class, 'assignTechnician'])->name('suggestions.assign-technician');
    Route::post('/suggestions/{suggestion}/assign-group', [AssignmentSuggestionActionController::class, 'assignGroup'])->name('suggestions.assign-group');
    Route::post('/suggestions/{suggestion}/manual-triage', [AssignmentSuggestionActionController::class, 'manualTriage'])->name('suggestions.manual-triage');
    Route::post('/suggestions/{suggestion}/recalculate', [AssignmentSuggestionActionController::class, 'recalculate'])->name('suggestions.recalculate');
    Route::get('/manual-analysis', [ManualAnalysisController::class, 'create'])->name('manual-analysis.create');
    Route::post('/manual-analysis', [ManualAnalysisController::class, 'store'])->middleware('throttle:manual-glpi-ai')->name('manual-analysis.store');
    Route::get('/audit', GlpiAiAuditController::class)->name('audit.index');
    Route::get('/settings', [GlpiAiSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [GlpiAiSettingsController::class, 'update'])->name('settings.update');
});
