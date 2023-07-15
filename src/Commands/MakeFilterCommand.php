<?php

namespace Mitoop\Query\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeFilterCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:filter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new filter class';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/filter.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Http\Filters';
    }
}
