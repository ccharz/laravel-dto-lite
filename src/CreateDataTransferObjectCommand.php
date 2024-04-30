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
    public function handle(Filesystem $filesystem): void
    {
        $stub = $filesystem->get($this->getStubPath());

        $replacements = $this->replacements();

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        $target_directory = App::basePath(str_replace('/', DIRECTORY_SEPARATOR, $this->option('directory')));

        if (! $filesystem->exists($target_directory)) {
            $filesystem->makeDirectory($target_directory, recursive: true);
        }

        $filesystem->put($target_directory.DIRECTORY_SEPARATOR.Str::ucfirst($this->argument('model')).'Data.php', $stub);
    }

    public function getStubPath(): string
    {
        return __DIR__.'/../stubs/DataTransferObject.php.stub';
    }

    public function replacements(): array
    {
        return [
            'CLASS' => Str::ucfirst($this->argument('model')).'Data',
            'NAMESPACE' => $this->option('data-namespace'),
            'MODEL' => '\\'.trim($this->option('model-namespace'), '\\').'\\'.$this->argument('model'),
            'VARIABLES' => implode(PHP_EOL, []),
        ];
    }
}
