<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Client\BillingController as ClientBillingController;
use App\Http\Controllers\Client\BriefController as ClientBriefController;
use App\Http\Controllers\Client\CarePlanController as ClientCarePlanController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DocumentController as ClientDocumentController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Controllers\Client\SupportRequestController as ClientSupportRequestController;
use App\Http\Controllers\Integrations\TelegramWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Owner\CarePlanController;
use App\Http\Controllers\Owner\CompanyController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\DocumentController as OwnerDocumentController;
use App\Http\Controllers\Owner\DocumentPackController;
use App\Http\Controllers\Owner\DocumentTemplateController;
use App\Http\Controllers\Owner\InvoiceController;
use App\Http\Controllers\Owner\LeadController;
use App\Http\Controllers\Owner\PaymentScheduleController;
use App\Http\Controllers\Owner\ProjectController as OwnerProjectController;
use App\Http\Controllers\Owner\ProviderProfileController;
use App\Http\Controllers\Owner\SupportRequestController as OwnerSupportRequestController;
use App\Http\Controllers\Owner\TeamController;
use App\Http\Controllers\Owner\WorkItemController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::post('/integrations/telegram/webhook', TelegramWebhookController::class)
    ->middleware('throttle:integrations')
    ->name('integrations.telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
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
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::prefix('owner')->name('owner.')->middleware(['auth', 'password.changed', 'role:staff'])->group(function () {
    Route::get('/', OwnerDashboardController::class)->name('dashboard');
    Route::get('settings/provider', [ProviderProfileController::class, 'edit'])->middleware('role:owner')->name('settings.provider.edit');
    Route::put('settings/provider', [ProviderProfileController::class, 'update'])->middleware('role:owner')->name('settings.provider.update');
    Route::get('document-builder', [DocumentPackController::class, 'create'])->middleware('permission:manage_documents')->name('document-pack.create');
    Route::post('document-builder', [DocumentPackController::class, 'store'])->middleware('permission:manage_documents')->name('document-pack.store');
    Route::get('documents/{document}/builder', [DocumentPackController::class, 'edit'])->middleware('permission:manage_documents')->name('document-pack.edit');
    Route::put('documents/{document}/builder', [DocumentPackController::class, 'update'])->middleware('permission:manage_documents')->name('document-pack.update');
    Route::resource('leads', LeadController::class)->except(['show', 'destroy'])->middleware('permission:manage_leads');
    Route::resource('companies', CompanyController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])->middleware('permission:manage_clients');
    Route::resource('projects', OwnerProjectController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:manage_projects');
    Route::post('projects/{project}/stages', [OwnerProjectController::class, 'storeStage'])->middleware('permission:manage_projects')->name('projects.stages.store');
    Route::patch('projects/{project}/stages/{stage}', [OwnerProjectController::class, 'updateStage'])->middleware('permission:manage_projects')->name('projects.stages.update');
    Route::delete('projects/{project}/stages/{stage}', [OwnerProjectController::class, 'destroyStage'])->middleware('permission:manage_projects')->name('projects.stages.destroy');
    Route::post('projects/{project}/attachments', [AttachmentController::class, 'store'])->middleware('permission:manage_projects')->name('projects.attachments.store');
    Route::resource('documents', OwnerDocumentController::class)->only(['index', 'create', 'store', 'show', 'update'])->middleware('permission:manage_documents');
    Route::post('documents/{document}/send', [OwnerDocumentController::class, 'send'])->middleware('permission:manage_documents')->name('documents.send');
    Route::post('documents/{document}/signed', [OwnerDocumentController::class, 'uploadSigned'])->middleware('permission:manage_documents')->name('documents.signed');
    Route::post('documents/{document}/confirm-signed', [OwnerDocumentController::class, 'confirmSigned'])->middleware('permission:manage_documents')->name('documents.confirm-signed');
    Route::get('documents/{document}/pdf', [OwnerDocumentController::class, 'pdf'])->middleware('permission:manage_documents')->name('documents.pdf');
    Route::resource('document-templates', DocumentTemplateController::class)->only(['index', 'store', 'update'])->middleware('permission:manage_documents');
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:manage_billing');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('permission:manage_billing')->name('invoices.send');
    Route::post('invoices/{invoice}/refresh-profile', [InvoiceController::class, 'refreshProfile'])->middleware('permission:manage_billing')->name('invoices.refresh-profile');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->middleware('permission:manage_billing')->name('invoices.payments.store');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:manage_billing')->name('invoices.void');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:manage_billing')->name('invoices.pdf');
    Route::get('projects/{project}/payment-schedule', [PaymentScheduleController::class, 'create'])->middleware('permission:manage_billing')->name('payment-schedules.create');
    Route::post('projects/{project}/payment-schedule', [PaymentScheduleController::class, 'store'])->middleware('permission:manage_billing')->name('payment-schedules.store');
    Route::post('payment-schedule-items/{item}/invoice', [PaymentScheduleController::class, 'invoice'])->middleware('permission:manage_billing')->name('payment-schedules.invoice');
    Route::resource('care-plans', CarePlanController::class)->only(['index', 'create', 'store', 'show', 'update'])->middleware('permission:manage_care');
    Route::post('care-plans/{carePlan}/activities', [CarePlanController::class, 'activity'])->middleware('permission:manage_care')->name('care-plans.activities.store');
    Route::get('requests', [OwnerSupportRequestController::class, 'index'])->middleware('permission:manage_requests')->name('requests.index');
    Route::get('requests/{supportRequest}', [OwnerSupportRequestController::class, 'show'])->middleware('permission:manage_requests')->name('requests.show');
    Route::put('requests/{supportRequest}', [OwnerSupportRequestController::class, 'update'])->middleware('permission:manage_requests')->name('requests.update');
    Route::post('requests/{supportRequest}/messages', [OwnerSupportRequestController::class, 'message'])->middleware('permission:manage_requests')->name('requests.messages.store');
    Route::post('requests/{supportRequest}/external', [OwnerSupportRequestController::class, 'external'])->middleware('permission:manage_requests')->name('requests.external.store');
    Route::post('requests/{supportRequest}/change-order', [OwnerSupportRequestController::class, 'changeOrder'])->middleware('permission:manage_requests')->name('requests.change-order');
    Route::resource('work-items', WorkItemController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware('permission:manage_work_items');
    Route::patch('work-items/{workItem}/status', [WorkItemController::class, 'updateStatus'])->middleware('permission:manage_work_items')->name('work-items.status');
    Route::post('work-items/{workItem}/sync', [WorkItemController::class, 'sync'])->middleware('permission:manage_work_items')->name('work-items.sync');
    Route::post('work-items/{workItem}/archive', [WorkItemController::class, 'archive'])->middleware('permission:manage_work_items')->name('work-items.archive');
    Route::get('activity', [ActivityController::class, 'owner'])->middleware('permission:view_activity')->name('activity.index');
    Route::get('team', [TeamController::class, 'index'])->middleware('permission:manage_team')->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->middleware('permission:manage_team')->name('team.store');
    Route::put('team/{user}', [TeamController::class, 'update'])->middleware('permission:manage_team')->name('team.update');
});

Route::prefix('portal')->name('client.')->middleware(['auth', 'password.changed', 'role:client'])->group(function () {
    Route::get('/', ClientDashboardController::class)->name('dashboard');
    Route::get('projects', [ClientProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ClientProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/brief', [ClientBriefController::class, 'edit'])->name('brief.edit');
    Route::put('projects/{project}/brief', [ClientBriefController::class, 'update'])->name('brief.update');
    Route::post('projects/{project}/attachments', [AttachmentController::class, 'store'])->name('projects.attachments.store');
    Route::get('documents', [ClientDocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{document}', [ClientDocumentController::class, 'show'])->name('documents.show');
    Route::post('documents/{document}/decision', [ClientDocumentController::class, 'decide'])->name('documents.decision');
    Route::post('documents/{document}/signed', [ClientDocumentController::class, 'uploadSigned'])->name('documents.signed');
    Route::get('documents/{document}/pdf', [ClientDocumentController::class, 'pdf'])->name('documents.pdf');
    Route::post('projects/{project}/stages/{stage}/decision', [ClientDocumentController::class, 'decideStage'])->name('stages.decision');
    Route::get('billing', [ClientBillingController::class, 'index'])->name('billing.index');
    Route::get('billing/{invoice}', [ClientBillingController::class, 'show'])->name('billing.show');
    Route::get('billing/{invoice}/pdf', [ClientBillingController::class, 'pdf'])->name('billing.pdf');
    Route::get('care-support', [ClientCarePlanController::class, 'index'])->name('care-plans.index');
    Route::get('care-support/{carePlan}', [ClientCarePlanController::class, 'show'])->name('care-plans.show');
    Route::get('requests', [ClientSupportRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/create', [ClientSupportRequestController::class, 'create'])->name('requests.create');
    Route::post('requests', [ClientSupportRequestController::class, 'store'])->name('requests.store');
    Route::get('requests/{supportRequest}', [ClientSupportRequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{supportRequest}/messages', [ClientSupportRequestController::class, 'message'])->name('requests.messages.store');
    Route::get('activity', [ActivityController::class, 'client'])->name('activity.index');
});
