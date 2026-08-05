<?php

namespace App\Platform\Modules\Console;

use App\Platform\Modules\Runtime\ModuleInstaller;
use Illuminate\Console\Command;

class InstallModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:module {module} {version}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install cloned module.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ModuleInstaller::complete($this->argument('module'), $this->argument('version'));

        return Command::SUCCESS;
    }
}
