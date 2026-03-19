# Acorn Example Package

[![Follow Roots](https://img.shields.io/badge/follow%20@rootswp-1da1f2?logo=twitter&logoColor=ffffff&message=&style=flat-square)](https://twitter.com/rootswp)
[![Sponsor Roots](https://img.shields.io/badge/sponsor%20roots-525ddc?logo=github&style=flat-square&logoColor=ffffff&message=)](https://github.com/sponsors/roots)

This repo can be used to scaffold an Acorn package. See the [Acorn Package Development](https://roots.io/acorn/docs/package-development/) docs for further information.

## Support us

Roots is an independent open source org, supported only by developers like you. Your sponsorship funds [WP Packages](https://wp-packages.org/) and the entire Roots ecosystem, and keeps them independent. Support us by purchasing [Radicle](https://roots.io/radicle/) or [sponsoring us on GitHub](https://github.com/sponsors/roots) — sponsors get access to our private Discord.

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

## Community

Keep track of development and community news.

- Join us on Discord by [sponsoring us on GitHub](https://github.com/sponsors/roots)
- Join us on [Roots Discourse](https://discourse.roots.io/)
- Follow [@rootswp on Twitter](https://twitter.com/rootswp)
- Follow the [Roots Blog](https://roots.io/blog/)
- Subscribe to the [Roots Newsletter](https://roots.io/subscribe/)
