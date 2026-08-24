<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Client\BriefController as ClientBriefController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Controllers\Owner\CompanyController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\LeadController;
use App\Http\Controllers\Owner\ProjectController as OwnerProjectController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/change-password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/change-password', [PasswordController::class, 'update'])->name('password.update');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
});

Route::prefix('owner')->name('owner.')->middleware(['auth', 'password.changed', 'role:owner'])->group(function () {
    Route::get('/', OwnerDashboardController::class)->name('dashboard');
    Route::resource('leads', LeadController::class)->except(['show', 'destroy']);
    Route::resource('companies', CompanyController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('projects', OwnerProjectController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('projects/{project}/stages/{stage}', [OwnerProjectController::class, 'updateStage'])->name('projects.stages.update');
    Route::post('projects/{project}/attachments', [AttachmentController::class, 'store'])->name('projects.attachments.store');
});

Route::prefix('portal')->name('client.')->middleware(['auth', 'password.changed', 'role:client'])->group(function () {
    Route::get('/', ClientDashboardController::class)->name('dashboard');
    Route::get('projects', [ClientProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ClientProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/brief', [ClientBriefController::class, 'edit'])->name('brief.edit');
    Route::put('projects/{project}/brief', [ClientBriefController::class, 'update'])->name('brief.update');
    Route::post('projects/{project}/attachments', [AttachmentController::class, 'store'])->name('projects.attachments.store');
});
