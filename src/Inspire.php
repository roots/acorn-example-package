<?php

namespace Roots\AcornExamplePackage;

use Illuminate\Support\Arr;

class Inspire
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
        $quotes = config('inspire.quotes');

        return Arr::random($quotes);
    }
}
