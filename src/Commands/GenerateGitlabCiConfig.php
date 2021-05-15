<?php

namespace Talvbansal\GitlabCiConfigGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Talvbansal\GitlabCiConfigGenerator\GitlabCiConfig;

class GenerateGitlabCiConfig extends Command
{
    protected $signature = 'gitlab-ci:generate';
    protected $description = 'Generate a gitlab ci/cd config file';

    private $phpVersion = '8.0';

    // js..
    private $jsDependencies = 'No';
    private $eslint = 'No';
    private $compileAssets = 'No';

    // syntax...
    private $phpCsFixer = 'No';
    private $laraStan = 'No';

    // testing...
    private $phpunit = 'No';

    /**
     * @var GitlabCiConfig
     */
    private $configGenerator;

    public function __construct(GitlabCiConfig $configGenerator)
    {
        parent::__construct();

        $this->configGenerator = $configGenerator;
    }

    public function handle()
    {
        if (! extension_loaded('yaml')) {
            $this->error('The php yaml extension is not enabled. Please install it and re-run this command.');

            return false;
        }

        if ($this->doesFileExist('.gitlab-ci.yml')) {
            if (! $this->confirm('A .gitlab-ci.yml already exists. Running this command will overwrite it are you sure you want to continue?')) {
                return false;
            }
        }

        $this->collectConfigItems()
            ->confirmConfigItems();

        while (! $this->confirm('Is the build configuration correct?')) {
            $this->collectConfigItems()
                ->confirmConfigItems();
        }

        if ($this->eslint === 'Yes' && $this->doesFileExist('.eslintrc.json')) {
            if (! $this->confirm('A .eslintrc.json already exists. Running this command will overwrite it are you sure you want to continue?')) {
                return false;
            }
        }

        if ($this->laraStan === 'Yes' && $this->doesFileExist('phpstan.neon')) {
            if (! $this->confirm('A phpstan.neon already exists. Running this command will overwrite it are you sure you want to continue?')) {
                return false;
            }
        }

        $this->installDependencies()
            ->buildConfig();
    }

    private function collectConfigItems() : self
    {
        $this->phpVersion = $this->choice('Which php version should we target?', ['8.0', '7.4', '7.3', '7.2'], 0);

        $this->jsDependencies = $this->choice('Does your project have js dependencies', ['Yarn', 'Npm', 'No'], 0);
        if ($this->jsDependencies !== 'No') {
            // check if eslint file exists and dont overwrite it
            $this->eslint = $this->choice('Create default ES Lint file', ['Yes', 'No'], 0);
            $this->compileAssets = $this->choice('Compile front end assets', ['Yes', 'No'], 0);
        }

        $this->phpCsFixer = $this->choice('Use laravel shift\'s FriendsOfPHP/PHP-CS-Fixer rules', ['Yes', 'No'], 0);
        $this->laraStan = $this->choice('Use nunomaduro/larastan for static analysis', ['Yes', 'No'], 0);
        $this->phpunit = $this->choice('Run phpunit tests', ['Yes', 'No'], 0);

        return $this;
    }

    private function confirmConfigItems() : self
    {
        $this->table(['Item', 'Value'], [
            ['PHP Version', $this->phpVersion],
            ['Frontend asset handling managed', $this->jsDependencies],
            ['Generate Eslint config', $this->eslint],
            ['Check .js and .vue files with', $this->eslint],
            ['Compile frontend assets with laravel-mix', $this->compileAssets],
            ['Check code style rules with PHP-CS-Fixer', $this->phpCsFixer],
            ['Perform Static analysis with Larastan', $this->laraStan],
            ['Run PHPUnit tests', $this->phpunit],
        ]);

        return $this;
    }

    private function installDependencies() : self
    {
        $this->info('Installing dependencies...');
        if ($this->phpCsFixer === 'Yes') {
            $this->composerInstall('friendsofphp/php-cs-fixer');

            $response = Http::get('https://gist.githubusercontent.com/laravel-shift/cab527923ed2a109dda047b97d53c200/raw/7108d407ce7feabf22730ee21332bb3f5dd49772/.php_cs.laravel.php');

            if ($response->status() !== 200) {
                $this->error(sprintf('Unable to download latest laravel shift rules for php-cs-fixer. [Status: %s]', $response->status()));
            } else {
                file_put_contents(base_path('.php_cs.php'), $response->body());
            }
        }

        if ($this->laraStan === 'Yes') {
            $this->composerInstall('nunomaduro/larastan --dev');
            $this->copyStub('phpstan.neon');
        }

        if ($this->jsDependencies !== 'No') {
            if ($this->eslint === 'Yes') {
                $this->jsInstall('eslint', 'eslint-plugin-vue', 'vue-template-compiler');
                $this->copyStub('.eslintrc.json');
            }
        }

        return $this;
    }

    private function copyStub(string $name): void
    {
        $response = copy(__DIR__.'/../stubs/'.$name, base_path($name));
        if ($response) {
            $this->info(sprintf('%s config file created', $name));
        } else {
            $this->error(sprintf('Unable to create %s config file', $name));
        }
    }

    private function jsInstall(...$args) : void
    {
        if ($this->jsDependencies === 'Npm') {
            $jsInstall = 'npm install --save-dev';
        } else {
            $jsInstall = 'yarn add --dev';
        }

        shell_exec(sprintf('%s %s', $jsInstall, implode(' ', $args)));
    }

    /**
     * use shell_exec since it returned the entire stream output as a string
     * exec only returns the last line...
     *
     * @param string $package
     */
    private function composerInstall(string $package)
    {
        shell_exec(sprintf('composer require %s --dev', $package));
    }

    private function doesFileExist(string $file) : bool
    {
        return file_exists(base_path($file));
    }

    private function buildConfig() : self
    {
        $yaml = $this->configGenerator->setPhpVersion($this->phpVersion)
            ->setConfig([
                'jsDependencies' => $this->jsDependencies,
                'eslint' => $this->eslint,
                'compileAssets' => $this->compileAssets,

                'phpCsFixer' => $this->phpCsFixer,
                'laraStan' => $this->laraStan,
                'phpunit' => $this->phpunit,
            ])
            ->generate();

        if (! file_put_contents(base_path('.gitlab-ci.yml'), $yaml)) {
            $this->error('Unable to create gitlab-ci.yml');
        }

        $this->info('Config generated and dependencies installed');

        return $this;
    }
}
