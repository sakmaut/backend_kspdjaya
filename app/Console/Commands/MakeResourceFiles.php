<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class MakeResourceFiles extends Command
{
    protected $signature = 'make:allfiles {name}';
    protected $description = 'Generate service, controller, model, and repository files';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));

        $baseNamespace = 'App\Http\Controllers\Payment';
        $basePath = app_path('Http/Controllers/Payment');

        $structure = [
            'Controller' => "C_$name",
            'Service'    => "S_$name",
            'Repository' => "R_$name",
        ];

        // Make Controller, Service, Repository via Artisan
        foreach ($structure as $folder => $className) {
            $relativePath = "Payment/$folder/$className";
            Artisan::call('make:controller', [
                'name' => $relativePath,
            ]);
            $this->info("Created: app/$relativePath.php");
        }

        // Handle Model manually
        $modelFolder = "$basePath/Model";
        $modelClass = "M_$name";
        $modelPath = "$modelFolder/$modelClass.php";

        if (!file_exists($modelFolder)) {
            mkdir($modelFolder, 0755, true);
        }

        $modelNamespace = "$baseNamespace\\Model";

        $modelContent = "<?php

namespace $modelNamespace;

use Illuminate\\Database\\Eloquent\\Model;

class $modelClass extends Model
{
    //
}
";

        file_put_contents($modelPath, $modelContent);
        $this->info("Created: $modelPath");
    }
}
