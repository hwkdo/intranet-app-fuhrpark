<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Commands;

use Hwkdo\IntranetAppFuhrpark\Models\DriverLicense;
use Illuminate\Console\Command;

class ImportDriverLicensesCommand extends Command
{
    protected $signature = 'fuhrpark:import-driver-licenses {file : Path to xlsx file}';

    protected $description = 'Import driver licenses from spreadsheet (replaces existing records)';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_readable($file)) {
            $this->error("File not readable: {$file}");

            return self::FAILURE;
        }

        $this->warn('This command expects a structured xlsx import — implement mapping for your file format.');
        $this->info('Clearing existing driver licenses...');
        DriverLicense::query()->delete();

        return self::SUCCESS;
    }
}
