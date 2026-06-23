<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateProjectContextCommand extends Command
{
    protected $signature = 'app:bundle-context {--output=contexto_projeto.md}';
    protected $description = 'Consolida os arquivos principais do projeto em um único arquivo Markdown para contexto.';

    // Pastas e arquivos que queremos incluir
    protected $allowedDirectories = [
        'app',
        'config',
        'database/migrations',
        'database/seeders',
        'routes'
    ];

    // Extensões de arquivos permitidas
    protected $allowedExtensions = ['php', 'json'];

    public function handle()
    {
        $outputFile = $this->option('output');
        $content = "# Contexto do Projeto Laravel\n\n";
        $content .= "Este arquivo contém a estrutura e o código-fonte principal da aplicação.\n\n";

        foreach ($this->allowedDirectories as $dir) {
            $path = base_path($dir);

            if (!File::exists($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if (!in_array($file->getExtension(), $this->allowedExtensions)) {
                    continue;
                }

                $relativeSubPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                
                $this->info("Processando: {$relativeSubPath}");

                $content .= "## Arquivo: {$relativeSubPath}\n";
                $content .= "```{$file->getExtension()}\n";
                $content .= File::get($file->getRealPath()) . "\n";
                $content .= "```\n\n";
            }
        }

        File::put(base_path($outputFile), $content);
        $this->info("Sucesso! Contexto gerado em: " . base_path($outputFile));
    }
}