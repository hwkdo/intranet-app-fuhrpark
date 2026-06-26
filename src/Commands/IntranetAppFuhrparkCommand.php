<?php

namespace Hwkdo\IntranetAppFuhrpark\Commands;

use Illuminate\Console\Command;

class IntranetAppFuhrparkCommand extends Command
{
    public $signature = 'intranet-app-fuhrpark';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
