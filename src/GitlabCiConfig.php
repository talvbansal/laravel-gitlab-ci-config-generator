<?php

namespace Talvbansal\GitlabCiConfigGenerator;

class GitlabCiConfig
{
    private $version;

    private $config = [];

    public function setPhpVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function setConfig(array $config = []): self
    {
        $this->config = $config;

        return $this;
    }

    private function dockerImage() : string
    {
        return sprintf('edbizarro/gitlab-ci-pipeline-php:%s', $this->version);
    }

    private function stages(): array
    {
        return [
            'preparation',
            'building',
            'syntax',
            'testing',
            'deploy',
        ];
    }

    public function generate()
    {
        $core = collect([
            'image' => $this->dockerImage(),
            'stages' => $this->stages(),
            'cache' => [
                'key' => '$CI_JOB_NAME-$CI_COMMIT_REF_SLUG',
            ],
            // placeholder to be replaced by the install_ext_yaml re-usable block
            'placeholder' => 'placeholder',
        ]);

        /*
         * Dont add ext-yaml to composer.json otherwise we need to add
         * php-yaml to the docker images in order to be able to install
         * composer dependencies...
         */
        $yaml = yaml_emit($core
            ->merge($this->composerDependenciesConfig())
            ->merge($this->jsConfig())
            ->merge($this->syntaxConfig())
            ->merge($this->testingConfig())
            ->toArray());

        $yaml = $this->addYamlExtension($yaml);

        return $yaml;
    }

    private function composerDependenciesConfig()
    {
        return [
            'composer' => [
                'stage' => 'preparation',
                'before_script' =>[
                    '*install_ext_yaml',
                ],
                'script' => [
                    'php -v',
                    'sudo composer self-update --2',
                    'cp .env.example .env',
                    'composer install --prefer-dist --no-ansi --no-interaction --no-progress --no-scripts --no-suggest',
                    'php artisan key:generate',
                ],
                'artifacts' => [
                    'paths' => [
                        'vendor/',
                        '.env',
                    ],
                    'expire_in' => '1 days',
                    'when' => 'always',
                ],
                'cache' => [
                    'paths' => [
                        'vendor/',
                    ],
                ],
            ],
        ];
    }

    private function jsConfig()
    {
        if ($this->config['jsDependencies'] === 'No') {
            return;
        }

        if ($this->config['jsDependencies'] === 'Yarn') {
            $packageManager = 'yarn';
            $install = 'yarn install --pure-lockfile';
        } else {
            $packageManager = 'npm';
            $install = 'npm ci';
        }

        $js = [
            'js' => [
                'stage' => 'preparation',
                'script' => [
                    $packageManager.' -v',
                    $install,
                ],
                'artifacts' => [
                    'paths' => [
                        'node_modules/',
                    ],
                    'expire_in' => '1 days',
                    'when' => 'always',
                ],
                'cache' => [
                    'paths' => [
                        'node_modules/',
                    ],
                ],
            ],
        ];

        if ($this->config['compileAssets'] === 'Yes') {
            $js['build-dev-assets'] = [
                'stage' => 'building',
                'dependencies' => [
                    'composer',
                    'js',
                ],
                'script' => [
                    'cp .env.example .env',
                    $packageManager.' -v',
                    $packageManager.' run dev --progress false',
                ],
                'artifacts' => [
                    'paths' => [
                        'public/css',
                        'public/js',
                        'public/fonts',
                        'public/mix-manifest.json',
                    ],
                    'expire_in' => '1 days',
                    'when' => 'always',
                ],
                'except' => ['master'],
            ];

            $js['build-production-assets'] = [
                'stage' => 'building',
                'dependencies' => [
                    'composer',
                    'js',
                ],
                'script' => [
                    'cp .env.example .env',
                    $packageManager.' -v',
                    $packageManager.' run production --progress false',
                ],
                'artifacts' => [
                    'paths' => [
                        'public/css',
                        'public/js',
                        'public/fonts',
                        'public/mix-manifest.json',
                    ],
                    'expire_in' => '1 days',
                    'when' => 'always',
                ],
                'only' => ['master'],
            ];
        }

        if ($this->config['eslint'] === 'Yes') {
            $js['eslint'] = [
                'stage' => 'syntax',
                'dependencies' => ['js'],
                'script' => [
                    './node_modules/.bin/eslint resources/js/ --ext .js,.vue',
                ],
            ];
        }

        return $js;
    }

    private function syntaxConfig()
    {
        if ($this->config['phpCsFixer'] === 'No') {
            return;
        }

        return [
            'php-cs-fixer' => [
                'stage' => 'syntax',
                'dependencies' => [
                    'composer',
                ],
                'script' => [
                    './vendor/bin/php-cs-fixer fix --config=.php_cs.php --verbose --diff --dry-run',
                ],
            ],
        ];
    }

    public function testingConfig()
    {
        $testing = [];

        if ($this->config['phpunit'] === 'Yes') {
            $testing['phpunit'] = [
                'stage' => 'testing',
                'dependencies' => [
                    'composer',
                ],
                'before_script' => [
                    '*install_ext_yaml',
                ],
                'script' => [
                    'php -v',
                    'cp .env.example .env',
                    'composer install --prefer-dist --no-ansi --no-interaction --no-progress --no-scripts',
                    'composer dump',
                    'php artisan key:generate',
                    'php artisan storage:link',
                    'sudo cp /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.bak',
                    'echo "" | sudo tee /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini',
                    './vendor/phpunit/phpunit/phpunit --version',
                    'php -d short_open_tag=off ./vendor/phpunit/phpunit/phpunit -v --colors=never --stderr --exclude-group integration --log-junit=tests.xml',
                    'sudo cp /usr/local/etc/php/conf.d/docker-php-ext-xdebug.bak /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini',
                ],
                'artifacts' => [
                    'paths' => [
                        './storage/logs',
                        './tests.xml',
                    ],
                    'expire_in' => '1 days',
                    'when' => 'on_failure',
                    'reports' => [
                        'junit' => './tests.xml'
                    ],
                ],
            ];
        }

        if ($this->config['laraStan'] === 'Yes') {
            $testing['larastan'] = [
                'stage' => 'testing',
                'dependencies' => ['composer'],
                'script' => [
                    './vendor/bin/phpstan analyse',
                ],
            ];
        }

        if (empty($testing)) {
            return;
        }

        return $testing;
    }

    private function addYamlExtension(string $yaml)
    {
        $yaml = str_replace("'*install_ext_yaml'", '*install_ext_yaml', $yaml);

        // this is raw yaml mak sure its only indented by 2 spaces...
        $installExtYaml = '
.install_ext_yaml: &install_ext_yaml |
  sudo apt-get update -yqq
  sudo apt install gcc make libyaml-dev -y
  sudo pecl install yaml
  echo "extension=yaml.so" | sudo tee -a /usr/local/etc/php/conf.d/docker-php-ext-yaml.ini
  sudo docker-php-ext-enable yaml
  ';
        $yaml = str_replace('placeholder: placeholder', $installExtYaml, $yaml);

        return $yaml;
    }
}
