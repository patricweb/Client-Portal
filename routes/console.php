<?php

use App\Models\Invoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Invoice::whereNotIn('status', ['draft', 'paid', 'void'])
        ->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
})->dailyAt('01:00')->name('mark-invoices-overdue');

Schedule::command('care:generate-invoices')->dailyAt('02:00')->withoutOverlapping();
