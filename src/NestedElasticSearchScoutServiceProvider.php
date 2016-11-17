<?php

namespace CF\Scout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Elasticsearch\ClientBuilder as Elasticsearch;


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
            $es = Elasticsearch::fromConfig(config('scout.elasticsearch.config'));

            return new Engines\NestedElasticSearchEngine($es, config('scout.elasticsearch.index'));
        });
    }
}
