<?php

namespace App\Providers;

use App\Contracts\CRMCampaignEmailProvider;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Quotation;
use App\Models\RecordingSession;
use App\Models\TrackStageProgress;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectStagePolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RecordingSessionPolicy;
use App\Policies\TrackStageProgressPolicy;
use App\Services\CRM\ResendCampaignEmailProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        RecordingSession::class => RecordingSessionPolicy::class,
        ProjectStage::class => ProjectStagePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        Project::class => ProjectPolicy::class,
        Quotation::class => QuotationPolicy::class,
        TrackStageProgress::class => TrackStageProgressPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CRMCampaignEmailProvider::class, function ($app): CRMCampaignEmailProvider {
            return $app->make(ResendCampaignEmailProvider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });
    }
}
