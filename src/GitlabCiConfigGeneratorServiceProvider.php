<?php

namespace Talvbansal\GitlabCiConfigGenerator;

use Illuminate\Support\ServiceProvider;
use Talvbansal\GitlabCiConfigGenerator\Commands\GenerateGitlabCiConfig;

class GitlabCiConfigGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateGitlabCiConfig::class
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {

    }
}
