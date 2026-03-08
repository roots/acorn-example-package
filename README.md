# Acorn Example Package

This repo can be used to scaffold an Acorn package. See the [Acorn Package Development](https://roots.io/acorn/docs/package-development/) docs for further information.

## Installation

You can install this package with Composer:

```bash
composer require vendor-name/example-package
```

When using this repository as a package scaffold, run the configure script after creating the package:

```bash
php configure.php
```

For automation or CI, you can run it non-interactively:

```bash
php configure.php --no-interaction --author-name="Your Name" --author-email="you@example.com" --vendor-slug="your-vendor" --vendor-namespace="YourVendor" --package-slug="your-package" --class-name="YourPackage" --package-description="Your package description"
```

To preview changes without writing files:

```bash
php configure.php --dry-run
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
