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
    protected $signature = 'screenshot:install
                            {--browser-version= : Install a complete Chrome version instead of the latest stable release}
                            {--update : Check for and install the latest stable Chrome release}';

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
        $this->components->info('Checking Chrome for Testing...');

        try {
            $previous = $installer->path();
            $path = $installer->install($this->option('browser-version'), $this->option('update'));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($path === $previous ? 'Chrome is already installed.' : 'Chrome is ready.');
        $this->line($path);

        if (config('screenshot.browser.binary')) {
            $this->components->warn('Your configured screenshot.browser.binary still takes precedence over this installation.');
        }

        if ($path !== $previous) {
            $this->components->info('Restart long-running queue workers to use this installation. Close shared browsers before switching versions.');
        }

        return self::SUCCESS;
    }
}
