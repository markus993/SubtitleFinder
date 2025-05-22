<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CodeAnalyseCommand extends Command
{
    protected $signature = 'code:analyse {--fix : Fix the issues automatically}';

    protected $description = 'Run code analysis tools';

    public function handle()
    {
        $this->info('Running code analysis...');

        // Run Laravel Pint
        $this->info('Running Laravel Pint...');
        $pintPath = base_path('vendor/bin/pint');
        if (PHP_OS_FAMILY === 'Windows') {
            $pintPath = str_replace('/', '\\', $pintPath);
        }

        $pintProcess = new Process([
            'php',
            $pintPath,
            '--config='.base_path('pint.json'),
            $this->option('fix') ? '' : '--test',
        ]);

        $pintProcess->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        // Run PHPStan
        $this->info('Running PHPStan...');
        $phpstanPath = base_path('vendor/bin/phpstan');
        if (PHP_OS_FAMILY === 'Windows') {
            $phpstanPath = str_replace('/', '\\', $phpstanPath);
        }

        $phpstanProcess = new Process([
            'php',
            $phpstanPath,
            'analyse',
            '--memory-limit=2G',
            '--configuration='.base_path('phpstan.neon'),
            'app',
            'config',
            'database',
            'routes',
            'tests',
        ]);

        $phpstanProcess->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if ($pintProcess->isSuccessful() && $phpstanProcess->isSuccessful()) {
            $this->info('Code analysis completed successfully!');

            return 0;
        }

        $this->error('Code analysis found issues!');
        if (! $pintProcess->isSuccessful()) {
            $this->line('Pint errors:');
            $this->line($pintProcess->getErrorOutput());
        }
        if (! $phpstanProcess->isSuccessful()) {
            $this->line('PHPStan errors:');
            $this->line($phpstanProcess->getErrorOutput());
        }

        return 1;
    }
}
