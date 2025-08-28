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
        $inputName = $this->argument('name'); // contoh: Slik/Slik
        $segments = explode('/', $inputName);
        $rawName = array_pop($segments); // ambil nama class (Slik)
        $name = Str::studly($rawName);
        $subFolder = implode('/', array_map([Str::class, 'studly'], $segments)); // jadi Slik

        // Base namespace dan path
        $baseNamespace = 'App\Http' . ($subFolder ? '\\' . str_replace('/', '\\', $subFolder) : '');
        $basePath = app_path('Http' . ($subFolder ? '/' . $subFolder : ''));

        // Namespace per komponen
        $controllerNamespace = "$baseNamespace\\Controller";
        $repoNamespace = "$baseNamespace\\Repository";
        $serviceNamespace = "$baseNamespace\\Service";
        $modelNamespace = "$baseNamespace\\Model";

        // Folder path
        $controllerFolder = "$basePath/Controller";
        $repoFolder = "$basePath/Repository";
        $serviceFolder = "$basePath/Service";
        $modelFolder = "$basePath/Model";

        // Class name
        $controllerClass = "C_$name";
        $repoClass = "R_$name";
        $serviceClass = "S_$name";
        $modelClass = "M_$name";

        // File path
        $controllerPath = "$controllerFolder/$controllerClass.php";
        $repoPath = "$repoFolder/$repoClass.php";
        $servicePath = "$serviceFolder/$serviceClass.php";
        $modelPath = "$modelFolder/$modelClass.php";

        // ===== Controller =====
        if (!file_exists($controllerFolder)) {
            mkdir($controllerFolder, 0755, true);
        }

        if (!file_exists($controllerPath)) {
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

        // ===== Repository =====
        if (!file_exists($repoFolder)) {
            mkdir($repoFolder, 0755, true);
        }

        if (!file_exists($repoPath)) {
            $repoContent = "<?php

namespace $repoNamespace;

use $modelNamespace\\$modelClass;

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

        // ===== Service =====
        if (!file_exists($serviceFolder)) {
            mkdir($serviceFolder, 0755, true);
        }

        if (!file_exists($servicePath)) {
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

        // ===== Model =====
        if (!file_exists($modelFolder)) {
            mkdir($modelFolder, 0755, true);
        }

        if (!file_exists($modelPath)) {
            $modelContent = "<?php

namespace $modelNamespace;

use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Support\\Str;

class $modelClass extends Model
{
    use HasFactory;

    protected \$table = '';
    protected \$fillable = [];
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
