# Acorn Example Package

This repo can be used to scaffold an Acorn package. See the [Acorn Package Development](https://roots.io/acorn/docs/package-development/) docs for further information.

## Installation

You can install this package with Composer:

```bash
composer require vendor-name/example-package
```

You can publish the config file with:

```shell
$ wp acorn vendor:publish --provider="VendorName\ExamplePackage\Providers\ExampleServiceProvider"
```

## Usage

From a Blade template:

```blade
@include('Example::example')
```

From WP-CLI:

```shell
$ wp acorn example
```
