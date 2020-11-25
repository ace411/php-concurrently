<p align="center">
  <img src="https://ucarecdn.com/28200921-29f6-4683-abf7-641cafbc2dfc/concurrentlybasic.png" width="450" height="auto" />
</p>

<h1 align="center">php-concurrently</h1>

<p align="center">

[![License](https://poser.pugx.org/chemem/php-concurrently/license)](//packagist.org/packages/chemem/php-concurrently)
[![Build Status](https://travis-ci.org/ace411/php-concurrently.svg?branch=master)](https://travis-ci.org/ace411/php-concurrently)
[![Latest Stable Version](https://poser.pugx.org/chemem/php-concurrently/v)](//packagist.org/packages/chemem/php-concurrently)
[![composer.lock](https://poser.pugx.org/chemem/php-concurrently/composerlock)](//packagist.org/packages/chemem/php-concurrently)

</p>

A PHP version of [concurrently](https://npmjs.com/package/concurrently) written atop ReactPHP and RxPHP.

## Requirements

- PHP 7.2 or higher

## Rationale

Running multiple processes - especially those of the long-running variety - in distinct terminals is a practice that can become intractable, irrespective of Programming Language preferences. PHP, a language often derided for a lack of a rich assortment of programming artifacts, is despite the ill-conceived derision, an enabler of concurrency - via userspace tools like [ReactPHP](https://reactphp.org) and [RxPHP](https://github.com/ReactiveX/RxPHP).

The impetus for creating and maintaining `php-concurrently` is, therefore, a combination of strivings: a desire to harness existent language ecosystem tools to make running multiple processes concurrently - in a single terminal window - possible.

## Installation

Though it is possible to clone the repo, Composer remains the best tool for installing `php-concurrently`. To install the package via Composer, type the following in a console of your choosing.

```sh
$ composer global require chemem/php-concurrently
```

## Basic Usage

`php-concurrently` is a console application whose usage rubric follows a familiar `concurrently [options] [arguments]` pattern. Shown below is a simple example to concurrently run two PHP processes - defined in the `scipts` section of a `composer.json` file - with `php-concurrently`.

```sh
$ concurrently "composer server:run,composer worker:run"
```

It is possible to add to a `composer.json` file - a `php-concurrently`-executable directive - in a manner akin to defining `concurrently` directives in `package.json` files.

```json
{
  "scripts": {
    "app:run": "concurrently \"composer server:run, composer worker:run\"",
    "server:run": "php -f server.php 4000",
    "worker:run": "php -f worker.php"
  }
}
```

## Dealing with Problems

Endeavor to create an issue on GitHub when the need arises or send an email to lochbm@gmail.com.

## Contributing

Consider buying me a coffee if you appreciate the offerings of the project and/or would like to provide more impetus for me to continue working on it.

<a href="https://www.buymeacoffee.com/agiroLoki" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/lato-white.png" alt="Buy Me A Coffee" style="height: 51px !important;width: 217px !important;" /></a>
