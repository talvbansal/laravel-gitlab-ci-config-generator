# Generate a gitlab-ci config for your laravel project

[![Latest Version on Packagist](https://img.shields.io/packagist/v/talvbansal/laravel-gitlab-ci-config-generator.svg?style=flat-square)](https://packagist.org/packages/talvbansal/laravel-gitlab-ci-config-generator)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/talvbansal/laravel-gitlab-ci-config-generator/run-tests?label=tests)](https://github.com/talvbansal/laravel-gitlab-ci-config-generator/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/talvbansal/laravel-gitlab-ci-config-generator.svg?style=flat-square)](https://packagist.org/packages/talvbansal/laravel-gitlab-ci-config-generator)

A package to generate a gitlab ci config as well as pulling in dependencies for some sane default pipelines.

More information about what this sets up for you can be found [here](https://www.talvbansal.me/blog/in-depth-gitlab-ci-cd-with-laravel-apps/)

## Installation

You can install the package via composer:

```bash
composer require talvbansal/laravel-gitlab-ci-config-generator --dev
```

## Usage

``` php
php artisan gitlab-ci:generate
```

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Talv Bansal](https://github.com/talvbansal)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
