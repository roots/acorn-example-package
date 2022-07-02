<?php

namespace Roots\AcornExamplePackage\Console;

use Roots\Acorn\Console\Commands\Command;
use Roots\AcornExamplePackage\Facades\Inspire;

class InspireCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'example:inspire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve a random inspirational quote.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info(Inspire::getQuote());
    }
}
