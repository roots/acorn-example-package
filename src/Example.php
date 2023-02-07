<?php

namespace VendorName\ExamplePackage;

use Illuminate\Support\Arr;
use Roots\Acorn\Application;

class Example
{
    /**
     * The application instance.
     *
     * @var \Roots\Acorn\Application
     */
    protected $app;

    /**
     * Create a new Example instance.
     *
     * @param  \Roots\Acorn\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Retrieve a random inspirational quote.
     *
     * @return string
     */
    public function getQuote()
    {
        return Arr::random(
            config('example.quotes')
        );
    }
}
