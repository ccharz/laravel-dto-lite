<?php

namespace Ccharz\DtoLite;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class CreateDataTransferObjectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:dto {model} {--directory=app/Data} {--data-namespace=App\\Data} {--model-namespace=App\\Models}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a data transfer object for a model';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $filesystem): int
    {
        $model = $this->argument('model');

        $data_namespace = $this->option('data-namespace');

        $model_namespace = $this->option('model-namespace');

        $directory = $this->option('directory') ?? 'app/Data';

        assert(is_string($directory) && is_string($model) && is_string($data_namespace) && is_string($model_namespace));

        $stub = $filesystem->get($this->getStubPath());

        $replacements = $this->replacements($model, $data_namespace, $model_namespace);

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        $target_directory = App::basePath(str_replace('/', DIRECTORY_SEPARATOR, $directory));

        if (! $filesystem->exists($target_directory)) {
            $filesystem->makeDirectory($target_directory, recursive: true);
        }

        $filesystem->put($target_directory.DIRECTORY_SEPARATOR.Str::ucfirst($model).'Data.php', $stub);

        return Command::SUCCESS;
    }

    public function getStubPath(): string
    {
        return __DIR__.'/../stubs/DataTransferObject.php.stub';
    }

    /**
     * @return array<string,string>
     */
    public function replacements(string $model, string $data_namespace, string $model_namespace): array
    {
        return [
            'CLASS' => Str::ucfirst($model).'Data',
            'NAMESPACE' => $data_namespace,
            'MODEL' => '\\'.trim($model_namespace, '\\').'\\'.$model,
            'VARIABLES' => implode(PHP_EOL, []),
        ];
    }
}
