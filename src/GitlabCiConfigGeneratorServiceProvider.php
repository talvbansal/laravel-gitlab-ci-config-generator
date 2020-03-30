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
            $this->app->bind('command.gitlab-ci:generate', GenerateGitlabCiConfig::class);

            $this->commands([
                'command.gitlab-ci:generate',
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
