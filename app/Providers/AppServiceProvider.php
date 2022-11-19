<?php

namespace App\Providers;

use App\Services\PollihubTTNDownlinkService;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use App\HiveFactory;
use App\ChecklistFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * Paginate a standard Laravel Collection.
         *
         * @param int $perPage
         * @param int $total
         * @param int $page
         * @param string $pageName
         * @return array
         */
        Collection::macro('paginate', function($perPage, $page = null, $output_array=false, $total = null, $pageName = 'page') {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

            $items = $this->forPage($page, $perPage); 
            
            if ($output_array) // make sure no start indexes > 0 are provided, so output is not rendered as object, but as array of objects
                $items = Collection::make(array_values($items->toArray())); 

            $paginator = new LengthAwarePaginator(
                $items,
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );

            return $paginator;
        });
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
