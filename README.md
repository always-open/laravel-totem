<p align="center">
  <img src="https://github.com/codestudiohq/laravel-totem/blob/8.0/resources/assets/img/totem.png?raw=true" alt="Laravel Totem"/>
</p>
<p align="center">
<img src="https://github.com/codestudiohq/laravel-totem/workflows/Laravel/badge.svg?branch=8.0" alt="Build Status">
<a href="https://packagist.org/packages/studio/laravel-totem"><img src="https://poser.pugx.org/studio/laravel-totem/license.svg" alt="License"></a>
</p>

# Introduction

[![Join the chat at https://gitter.im/laravel-totem/Lobby](https://badges.gitter.im/laravel-totem/Lobby.svg)](https://gitter.im/laravel-totem/Lobby?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Manage your `Laravel Schedule` from a pretty dashboard. Schedule your `Laravel Console Commands` to your liking. Enable/Disable scheduled tasks on the fly without going back to your code again.

## Documentation

#### Compatiblity Matrix

| <span align="left">Laravel</span> | <span align="left">Totem</span> |
|:----------------------------------|--------------------------------:|
| 12.x                              |                            11.x |
| 11.x                              |                            11.x |
| 10.x                              |                            10.x |

#### Installing

`Totem` requires Laravel v10 and above, please refer to the above table for compatability. Use composer to install totem to your Laravel project

```
composer require studio/laravel-totem
```

> Laravel Totem supports auto package discovery for Laravel v5.5+, therefore service provider registration is not required in Laravel v5.5+

Add `TotemServiceProvider` to the `providers` array of your Laravel v5.4 application's config/app.php

```php
Studio\Totem\Providers\TotemServiceProvider::class,
```

Once `Laravel Totem` is installed & registered,

- Run the migration

```
php artisan migrate
```

- Publish `Totem` assets to your public folder using the following command

```
php artisan totem:assets
```

##### Table Prefix

Totems' tables use generic names which may conflict with existing tables in a project. To alleviate this the `.env` param `TOTEM_TABLE_PREFIX` can be set which will apply a prefix to all of Totems tables and their models.

#### Updating

Please republish totem assets after updating totem to a new version

```
php artisan totem:assets
```

#### Configuration

##### Cron Job

This package assumes that you have a good understanding of [Laravel's Task Scheduling](https://laravel.com/docs/5.4/scheduling) and [Laravel Console Commands](https://laravel.com/docs/5.4/artisan#writing-commands). Before any of this works please make sure you have a cron running as follows:

```
* * * * * php /path-to-your-project/artisan schedule:run >> /dev/null 2>&1
```

##### Web Dashboard

`Laravel Totem`'s dashboard is inspired by `Laravel Horizon`. Just like Horizon you can configure authentication to `Totem`'s dashboard. Add the following to the boot method of your AppServiceProvider or wherever you might seem fit.

```php
use Studio\Totem\Totem;

Totem::auth(function($request) {
    // return true / false . For e.g.
    return Auth::check();
});
```

By default Totem's dashboard only works in local environment. To view the dashboard point your browser to /totem of your app. For e.g. laravel.dev/totem.

##### Filter Commands Dropdown

By default `Totem` outputs all Artisan commands on the Create/Edit tasks. To make this dropdown more concise there is a filter config feature that can be set in the `totem.php` config file.

Example filters

```php
'artisan' => [
    'command_filter' => [
        'stats:*',
        'email:daily-reports'
    ],
],
```

This feature uses [fnmatch](http://php.net/manual/en/function.fnmatch.php) syntax to filter displayed commands. `stats:*` will match all Artisan commands that start with `stats:` while `email:daily-reports` will only match the command named `email:daily-reports`.

This filter can be used as either a whitelist or a blacklist. By default it acts as a whitelist but an option flag can be set to instead act as a blacklist.

```php
'artisan' => [
    'command_filter' => [
        'stats:*',
        'email:daily-reports'
    ],
    'whitelist' => true,
],

```

If the value of whitelist is `false` then the filter acts as a blacklist.

`'whitelist' => false`

#### Middleware

`Laravel Totem` uses the default web and api middleware but if customization is required the middleware can be changed by setting the appropriate `.env` value. These values can be found in `config/totem.php`.

#### Making Commands available in `Laravel Totem`

All artisan commands can be scheduled. If you want to hide a command from Totem make sure you have the `hidden` attribute set to true in your command. For e.g.

```php
protected $hidden = true;
```

From L5.5 onwards all commands are auto registered, so this wouldn't be a problem.

#### Command Parameters

If your command requires arguments or options please use the optional command parameters field. You can provide parameters to your command as a string in the following manner

```text
name=john.doe --greetings='Welcome to the new world'
```

In the example above, name is an argument while greetings is an option

#### Console Command

In addition to the dashboard, Totem provides an artisan command to view a list of scheduled task.

```
php artisan schedule:list
```

### Screenshots

##### Task List

<img src="https://github.com/codestudiohq/laravel-totem/blob/1.0/public/img/screenshots/tasks.png?raw=true" alt="Task List"/>

##### Task Details

<img src="https://github.com/codestudiohq/laravel-totem/blob/1.0/public/img/screenshots/task-details.png?raw=true" alt="Task List"/>

##### Edit Task

<img src="https://github.com/codestudiohq/laravel-totem/blob/1.0/public/img/screenshots/edit-task.png?raw=true" alt="Task List"/>

##### Artisan Command to view scheduled tasks

<img src="https://github.com/codestudiohq/laravel-totem/blob/1.0/public/img/screenshots/artisan.png?raw=true" alt="Task List"/>

## Changelog

Important versions listed below. Refer to the [Changelog](CHANGELOG.md) for a full history of the project.

## Credits

- [Roshan Gautam](https://twitter.com/@roshangautam)
- [OSS Contributors](https://github.com/codestudiohq/laravel-totem/graphs/contributors)

Bug reports, feature requests, and pull requests can be submitted by following our [Contribution Guide](CONTRIBUTING.md).

## Contributing & Protocols

- [Versioning](CONTRIBUTING.md#versioning)
- [Coding Standards](CONTRIBUTING.md#coding-standards)
- [Pull Requests](CONTRIBUTING.md#pull-requests)

## License

This software is released under the [MIT](LICENSE) License.

© 2020 Roshan Gautam, All rights reserved.
