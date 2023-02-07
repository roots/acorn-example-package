<?php

namespace VendorName\ExamplePackage\Facades;

use Illuminate\Support\Facades\Facade;

class Example extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'Example';
    }
}
