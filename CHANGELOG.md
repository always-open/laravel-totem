# Laravel Totem Change Log

This project follows [Semantic Versioning](CONTRIBUTING.md).

---

## v12.0.2 - 04/21/2026

### Fixed

- Empty output popup on the task-view page. The `<task-output>` Vue component received an empty `:output` prop because `@json()` produced a JSON literal whose outer `"` delimiters collided with the HTML attribute's own `"` wrappers, making the rendered HTML malformed. Replaced with `Js::from()`, Laravel's purpose-built helper for embedding JS expressions inside HTML attributes. (PR #____)

## v11.0.0 - 03/27/2024

- Add Laravel 11.x Support
- Update Change Log

## v10.0.0 - 03/17/2023

- Add Laravel 10.x Support
- Update Change Log

## v9.0.0 - 06/29/2022

- Add Laravel 9.x Support
- Update Change Log

## v8.0.0 - 10/30/2020

- Add Laravel 8.x Support
- Update Change Log

## v7.0.0 - 04/17/2020

- Add Laravel 7.x Support
- Update Change Log

## v6.0.0 - 10/26/2019

- Add Laravel 6.x Support
- Update Change Log

## v5.0.0 - 03/03/2019

- Add Laravel 5.8 Support
- Update Change Log

## v4.0.3 - 01/24/2019

- Fix constant definition typo in service provider

## v4.0.2 - 12/20/2018

- Add support for configurable DB connection
- Cache table prefix to avoid multiple and frequent DB queries

## v4.0.1 - 11/08/2018

- Fix table prefix issue that surfaced with Laravel 5.7.12
- Fix foreign key database migrations

## v4.0.0 - 09/05/2018

- Add Laravel 5.7 compatiblity
- Use null coalescing operator in blade file instead of or operator
- Update changelog and readme

## v3.0.0 - 02/27/2018

- Add support for Laravel 5.6

## v2.0.0 - 09/03/2017

- Add support for Laravel 5.5

## v1.0.0 - 09/03/2017

- Initial stable release for Laravel 5.4
