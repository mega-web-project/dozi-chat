<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => ['auth:sanctum'], // IMPORTANT
            'prefix' => 'api/v1',             // MUST match frontend
        ]);

        require base_path('routes/channels.php');
    }
}
