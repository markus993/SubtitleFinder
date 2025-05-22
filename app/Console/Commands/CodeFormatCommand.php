<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CodeFormatCommand extends Command
{
    protected $signature = 'code:format {--test : Only check formatting without making changes}';

    protected $description = 'Format code using Laravel Pint';

    public function handle()
    {
        $this->info('Running code formatter...');

        $pintPath = base_path('vendor/bin/pint');
        if (PHP_OS_FAMILY === 'Windows') {
            $pintPath = str_replace('/', '\\', $pintPath);
        }

        $process = new Process([
            'php',
            $pintPath,
            '--config='.base_path('pint.json'),
            '--verbose',
            $this->option('test') ? '--test' : '',
        ]);

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info('Code formatting completed successfully!');

            return 0;
        }

        $this->error('Code formatting found issues!');
        $this->line('Exit code: '.$process->getExitCode());
        $this->line('Error output: '.$process->getErrorOutput());

        return 1;
    }
}
