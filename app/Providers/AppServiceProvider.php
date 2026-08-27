<?php

namespace App\Providers;

use App\Models\CareActivity;
use App\Models\Document;
use App\Models\ExternalCommunication;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectBrief;
use App\Models\ProjectStage;
use App\Models\RequestMessage;
use App\Models\SupportRequest;
use App\Models\WorkItem;
use App\Observers\ActivityObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('integrations', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        foreach ([Project::class, ProjectStage::class, ProjectBrief::class, Document::class, Invoice::class, Payment::class, SupportRequest::class, RequestMessage::class, CareActivity::class, ExternalCommunication::class, WorkItem::class] as $model) {
            $model::observe(ActivityObserver::class);
        }
    }
}
