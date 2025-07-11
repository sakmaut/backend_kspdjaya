<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $classPath = str_replace('\\', '/', $name);
        $fullPath = app_path("Services/{$classPath}.php");

        if (file_exists($fullPath)) {
            $this->error("Service {$name} already exists!");
            return;
        }

        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Ambil namespace dinamis berdasarkan path
        $namespace = 'App\\Services\\' . str_replace('/', '\\', dirname($classPath));
        $className = class_basename($name);

        file_put_contents(
            $fullPath,
            <<<PHP
<?php

namespace {$namespace};

class {$className}
{
    //
}

PHP
        );

        $this->info("Service {$name} created successfully.");
    }
}
