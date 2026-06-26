<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Certificado;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Remove wrapping padrão do JSON ('data')
        JsonResource::withoutWrapping();

        // Observers
        User::observe(AuditObserver::class);
        Certificado::observe(AuditObserver::class);
    }
}