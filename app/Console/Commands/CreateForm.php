<?php

namespace App\Console\Commands;

use App\Generators\FormGenerator;
use Illuminate\Console\Command;

class CreateForm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:form {name} {el?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $elements = $this->argument('el');

        $generator = new FormGenerator($name, $elements);
        $generator->handle();
    }
}
