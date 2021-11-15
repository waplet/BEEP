<?php

namespace App\Providers;

use App\ChecklistFactory;
use App\Services\PollihubTTNDownlinkService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use App\HiveFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(HiveFactory::class, function() 
        {
            return new HiveFactory();  
        });

        // Register TTN Downlink service
        $this->app->singleton(
            PollihubTTNDownlinkService::class,
            function () {
                return new PollihubTTNDownlinkService(
                    $this->app->get(Client::class),
                    env('POLLIHUB_TTN_URL'),
                    env('POLLIHUB_TTN_TOKEN'),
                    env('POLLIHUB_TTN_APP_ID'),
                    env('POLLIHUB_TTN_WEBHOOK_ID')
                );
            }
        );

        $this->app->singleton(ChecklistFactory::class, function() 
        {
            return new ChecklistFactory();  
        });

        if ($this->app->environment() == 'local') 
        {
            $this->app->register('Appzcoder\CrudGenerator\CrudGeneratorServiceProvider');
        }
    }
}
