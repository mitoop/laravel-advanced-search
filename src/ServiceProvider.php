<?php

namespace Mitoop\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Mitoop\Query\Commands\MakeFilterCommand;

class ServiceProvider extends LaravelServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeFilterCommand::class,
            ]);
        }

        /**
         * @return \Illuminate\Database\Eloquent\Builder
         */
        Builder::macro('advanced', function (ConditionsGenerator $filter, array $params = null) {
            $filter->setParams($params ?: Request::all());

            return (new ConditionsBuilder($this, $filter()))();
        });
    }
}
