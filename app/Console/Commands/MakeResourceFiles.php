<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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

        // Struktur pembuatan: Controller, Service, Repository
        $structure = [
            'Controller' => "C_$name",
            'Service'    => "S_$name",
            'Repository' => "R_$name",
        ];

        // Buat Controller via Artisan (extends Controller Laravel)
        $controllerRelative = "Payment/Controller/C_$name";
        // Buat Controller manual dengan method resource default
        $controllerFolder = "$basePath/Controller";
        $controllerClass = "C_$name";
        $controllerPath = "$controllerFolder/$controllerClass.php";

        if (!file_exists($controllerFolder)) {
            mkdir($controllerFolder, 0755, true);
        }

        if (!file_exists($controllerPath)) {
            $controllerNamespace = "$baseNamespace\\Controller";

            $controllerContent = "<?php

namespace $controllerNamespace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class $controllerClass extends Controller
{
    public function index()
    {
        // TODO: implement index
    }

    public function show(\$id)
    {
        // TODO: implement show
    }

    public function store(Request \$request)
    {
        // TODO: implement store
    }

    public function update(Request \$request, \$id)
    {
        // TODO: implement update
    }

    public function destroy(\$id)
    {
        // TODO: implement destroy
    }
}
";

            file_put_contents($controllerPath, $controllerContent);
            $this->info("Created: $controllerPath");
        } else {
            $this->warn("Skipped (exists): $controllerPath");
        }

        $this->info("Created (via Artisan): app/Http/Controllers/$controllerRelative.php");

        // Buat Repository manual, inject Model
        $repoFolder = "$basePath/Repository";
        $repoClass = "R_$name";
        $repoPath = "$repoFolder/$repoClass.php";
        if (!file_exists($repoFolder)) {
            mkdir($repoFolder, 0755, true);
        }
        if (!file_exists($repoPath)) {
            $modelClass = "M_$name";
            $modelNamespace = "$baseNamespace\\Model\\$modelClass";
            $repoNamespace = "$baseNamespace\\Repository";

            $repoContent = "<?php

namespace $repoNamespace;

use $modelNamespace;

class $repoClass
{
    protected \$model;

    public function __construct($modelClass \$model)
    {
        \$this->model = \$model;
    }

    // TODO: Repository methods here
}
";
            file_put_contents($repoPath, $repoContent);
            $this->info("Created: $repoPath");
        } else {
            $this->warn("Skipped (exists): $repoPath");
        }

        // Buat Service manual, extend Repository, inject Repository
        $serviceFolder = "$basePath/Service";
        $serviceClass = "S_$name";
        $servicePath = "$serviceFolder/$serviceClass.php";
        if (!file_exists($serviceFolder)) {
            mkdir($serviceFolder, 0755, true);
        }
        if (!file_exists($servicePath)) {
            $serviceNamespace = "$baseNamespace\\Service";
            $repoNamespace = "$baseNamespace\\Repository";
            $repoClass = "R_$name";

            $serviceContent = "<?php

namespace $serviceNamespace;

use $repoNamespace\\$repoClass;

class $serviceClass extends $repoClass
{
    protected \$repository;

    public function __construct($repoClass \$repository)
    {
        \$this->repository = \$repository;
    }

    // TODO: Service methods here
}
";
            file_put_contents($servicePath, $serviceContent);
            $this->info("Created: $servicePath");
        } else {
            $this->warn("Skipped (exists): $servicePath");
        }

        // Buat Model manual dengan isi tetap seperti yang kamu minta
        $modelFolder = "$basePath/Model";
        $modelClass = "M_$name";
        $modelPath = "$modelFolder/$modelClass.php";

        if (!file_exists($modelFolder)) {
            mkdir($modelFolder, 0755, true);
        }

        if (!file_exists($modelPath)) {
            $modelNamespace = "$baseNamespace\\Model";

            $modelContent = "<?php

namespace $modelNamespace;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Support\\Str;

class $modelClass extends Model
{
    use HasFactory;
    protected \$table = '';
    protected \$fillable = [

    ];
    protected \$guarded = [];
    public \$incrementing = false;
    protected \$keyType = 'string';
    protected \$primaryKey = 'ID';
    public \$timestamps = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function (\$model) {
            if (\$model->getKey() == null) {
                \$model->setAttribute(\$model->getKeyName(), Str::uuid()->toString());
            }
        });
    }
}
";
            file_put_contents($modelPath, $modelContent);
            $this->info("Created: $modelPath");
        } else {
            $this->warn("Skipped (exists): $modelPath");
        }
    }
}
