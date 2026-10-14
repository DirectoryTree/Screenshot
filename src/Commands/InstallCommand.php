<?php

namespace DirectoryTree\Screenshot\Commands;

use DirectoryTree\Screenshot\Support\ChromeInstaller;
use Illuminate\Console\Command;
use Throwable;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screenshot:install {--browser-version= : Install a complete Chrome version instead of the latest stable release}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download and install Chrome for Screenshot';

    /**
     * Install Chrome without changing environment files or system packages.
     */
    public function handle(ChromeInstaller $installer): int
    {
        $this->components->info('Installing Chrome for Testing...');

        try {
            $path = $installer->install($this->option('browser-version'));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Chrome is ready.');
        $this->line($path);

        if (config('screenshot.binary')) {
            $this->components->warn('Your configured screenshot.binary still takes precedence over this installation.');
        }

        $this->components->info('Restart long-running queue workers to use this installation. Close shared browsers before switching versions.');

        return self::SUCCESS;
    }
}
