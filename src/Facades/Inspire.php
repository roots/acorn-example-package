<?php

namespace Roots\AcornExamplePackage\Facades;

use Illuminate\Support\Facades\Facade;

class Inspire extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'inspire';
    }
}
