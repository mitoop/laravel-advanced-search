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

        Builder::macro('advanced', function (ConditionsGenerator $filter = new ConditionsGenerator(), array $data = null) {
            $filter->setData($data ?: Request::all());

            return (new ConditionsBuilder($this))->attach($filter());
        });
    }
}
