<p align="center">
  <img src="https://github.com/codestudiohq/laravel-totem/blob/8.0/resources/assets/img/totem.png?raw=true" alt="Laravel Totem"/>
</p>
<p align="center">
<img src="https://github.com/always-open/laravel-totem/workflows/Laravel/badge.svg?branch=13.x" alt="Build Status">
<a href="https://packagist.org/packages/studio/laravel-totem"><img src="https://poser.pugx.org/studio/laravel-totem/license.svg" alt="License"></a>
</p>

# Introduction

Manage your `Laravel Schedule` from a pretty dashboard. Schedule your `Laravel Console Commands` to your liking. Enable/Disable scheduled tasks on the fly without going back to your code again.

## Documentation

#### Compatibility Matrix

| Laravel | Totem | Notes                                        |
|:--------|------:|:---------------------------------------------|
| 13.x    | 13.x  | Current                                      |
| 12.x    | 13.x  | Current release line — Laravel 13 runtime support |
| 12.x    | 12.x  | Maintained                                   |
| 11.x    | 12.x  | Maintained                                   |
| 11.x    | 11.x  | Maintained                                   |
| 10.x    | 11.x  | Maintained                                   |

#### Requirements

- PHP 8.3+
- Laravel 12.x or 13.x

Earlier Totem majors are maintained for legacy Laravel support:
- Totem 12.x supports Laravel 11.x and 12.x (PHP 8.2+).
- Totem 11.x supports Laravel 10.x, 11.x, and 12.x (PHP 8.2+).

#### Installing

Use composer to install Totem into your Laravel project:

```
composer require studio/laravel-totem
```

Once `Laravel Totem` is installed, run the migration and publish the assets:

```
php artisan migrate
php artisan totem:assets
```

#### Updating

Republish Totem assets after updating to a new version:

```
php artisan totem:assets
```

#### Configuration

##### Cron Job

This package assumes that you have a good understanding of [Laravel's Task Scheduling](https://laravel.com/docs/scheduling) and [Laravel Console Commands](https://laravel.com/docs/artisan#writing-commands). Before any of this works please make sure you have a cron running as follows:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

##### Web Dashboard

`Laravel Totem`'s dashboard is inspired by `Laravel Horizon`. Just like Horizon you can configure authentication to `Totem`'s dashboard. Add the following to the `boot` method of your `AppServiceProvider`:

```php
use Studio\Totem\Totem;

Totem::auth(function($request) {
    // return true / false . For e.g.
    return Auth::check();
});
```

By default Totem's dashboard only works in the local environment. To view the dashboard point your browser to `/totem` of your app.

##### Table Prefix

Totem's tables use generic names which may conflict with existing tables in a project. To alleviate this the `.env` param `TOTEM_TABLE_PREFIX` can be set which will apply a prefix to all of Totem's tables and their models.

##### Cache Store

By default Totem uses your application's default cache store. In environments where UI servers and background worker servers use separate cache clusters (e.g. different Redis instances), Totem's cache can become inconsistent — a bust event on one server won't clear the cache on the other.

Set `TOTEM_CACHE_STORE` to a named store from your `config/cache.php` that is accessible by all servers:

```
TOTEM_CACHE_STORE=redis-shared
```

Setting it to `array` disables cache persistence entirely (each request hits the database):

```
TOTEM_CACHE_STORE=array
```

Leaving it unset uses your application's default cache store (existing behaviour).

##### Filter Commands Dropdown

By default `Totem` outputs all Artisan commands on the Create/Edit tasks. To make this dropdown more concise there is a filter config feature that can be set in the `totem.php` config file.

Example filters:

```php
'artisan' => [
    'command_filter' => [
        'stats:*',
        'email:daily-reports'
    ],
],
```

This feature uses [fnmatch](http://php.net/manual/en/function.fnmatch.php) syntax to filter displayed commands. `stats:*` will match all Artisan commands that start with `stats:` while `email:daily-reports` will only match the command named `email:daily-reports`.

This filter can be used as either a whitelist or a blacklist. By default it acts as a whitelist but an option flag can be set to instead act as a blacklist:

```php
'artisan' => [
    'command_filter' => [
        'stats:*',
        'email:daily-reports'
    ],
    'whitelist' => false,
],
```

##### Middleware

`Laravel Totem` uses the `web` middleware by default. If customization is required the middleware can be changed by setting the `TOTEM_WEB_MIDDLEWARE` value in your `.env`. These values can be found in `config/totem.php`.

##### Notifications

Totem can send notifications when a task completes. Email notifications are included out of the box. For SMS (Vonage) or Slack notifications, install the relevant package:

```
composer require laravel/vonage-notification-channel
composer require laravel/slack-notification-channel
```

#### Making Commands Available in `Laravel Totem`

All artisan commands are available for scheduling. If you want to hide a command from Totem set the `hidden` attribute to `true` in your command:

```php
protected $hidden = true;
```

#### Command Parameters

If your command requires arguments or options use the optional command parameters field. You can provide parameters as a string in the following format:

```text
name=john.doe --greetings='Welcome to the new world'
```

In the example above, `name` is an argument while `greetings` is an option.

#### Console Command

Totem provides an artisan command to view a list of scheduled tasks:

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
- [OSS Contributors](https://github.com/always-open/laravel-totem/graphs/contributors)

Bug reports, feature requests, and pull requests can be submitted by following our [Contribution Guide](CONTRIBUTING.md).

## Contributing & Protocols

- [Versioning](CONTRIBUTING.md#versioning)
- [Coding Standards](CONTRIBUTING.md#coding-standards)
- [Pull Requests](CONTRIBUTING.md#pull-requests)

## License

This software is released under the [MIT](LICENSE) License.

© 2025 Roshan Gautam, All rights reserved.
