<?php

namespace CF\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;


class NestedElasticSearchScoutServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app[EngineManager::class]->extend('nestedelasticsearch', function () {
            return new NestedElasticSearchEngine;
        }
    }
}
