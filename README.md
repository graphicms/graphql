# GraphQL for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/graphicms/graphql.svg?style=flat-square)](https://packagist.org/packages/graphicms/graphql)
[![Total Downloads](https://img.shields.io/packagist/dt/graphicms/graphql.svg?style=flat-square)](https://packagist.org/packages/graphicms/graphql)

This is the package used by GraphiCMS to serve GraphQL schemas. It is based on [rebing/graphql-laravel](https://github.com/rebing/graphql-laravel) but we had do modify it so meet our needs (a lot of closures support, dynamic queries and mutations and so on). We'll try to keep it in sync with the base package as most as we can.

## Installation

You can install the package via composer:

```bash
composer require graphicms/graphql
```

## Usage

The service auto registers using Laravel's auto discovery package. You can publish the config files of the package using
```php
php artisan vendor:publish --provider="Graphicms\GraphQL\GraphQLServiceProvider" --tag=config
```

By default the package publishes the routes with `graphi/` prefix.  You can change this in the config file changing the keys `prefix` and `graphiql.prefix`.

This package also comes bundled with [Graphiql](https://github.com/graphql/graphiql), the browser IDE form GraphQl. You can check it out using http://[yoursite]//graphi/graphiql/{graphql_schema?} (this is also configurable).

All the functionality from [rebing/graphql-laravel](https://github.com/rebing/graphql-laravel) exists here too, but we also have a few dynamic queries, types and mutations support.

Documentation will come later.

## Warning
Though this package can be installed standalone, it is meant to be used alongside [GraphiCMS](https://github.com/graphicms/cms), the api-first CMS for Laravel. THE SOFTWARE IS PROVIDED "AS IS", like the License says.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email filip@pacurar.net instead of using the issue tracker.

## Credits

- [Filip Pacurar](https://github.com/filipac)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).