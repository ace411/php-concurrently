<?php

/**
 * bootstrap.php
 * file that contains the requisite artifacts with which to operationalize concurrently
 *
 * @package chemem/php-concurrently
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

foreach (
  [
    __DIR__ . '/../autoload.php',
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
  ] as $file
) {
  if (\file_exists($file)) {
    \define('AUTOLOAD_PHP_FILE', $file);
    break;
  }
}
if (!\defined('AUTOLOAD_PHP_FILE')) {
  \fwrite(
    STDERR,
    'You need to set up the project dependencies using the following commands:' . PHP_EOL .
    'wget http://getcomposer.org/composer.phar' . PHP_EOL .
    'php composer.phar install' . PHP_EOL
  );

  die(1);
}

require_once AUTOLOAD_PHP_FILE;

use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;

use function Chemem\Bingo\Functional\toException;
use function Chemem\Concurrently\Proc\applyColor;

$parser = new Console_CommandLine(
  [
    'description' => 'A PHP version of concurrently built atop ReactPHP and RxPHP',
    'version'     => '0.1.0',
  ]
);

$parser->addOption(
  'max_processes',
  [
    'short_name'  => '-m',
    'long_name'   => '--max-processes',
    'description' => 'define the maximum number of processes to execute concurrently',
    'action'      => 'StoreInt',
    'default'     => 1,
  ]
);

$parser->addOption(
  'no_spinner',
  [
    'long_name'   => '--no-spinner',
    'description' => 'prints output without the spinner',
    'action'      => 'StoreTrue',
    'default'     => false,
  ]
);

$parser->addOption(
  'no_color',
  [
    'long_name'   => '--no-color',
    'description' => 'disables colors from logging',
    'action'      => 'StoreTrue',
    'default'     => false,
  ]
);

$parser->addOption(
  'silent',
  [
    'short_name'  => '-s',
    'long_name'   => '--silent',
    'description' => 'run processes silently; without logging any output',
    'action'      => 'StoreTrue',
    'default'     => false,
  ]
);

$parser->addOption(
  'retries',
  [
    'short_name'  => '-r',
    'long_name'   => '--retries',
    'description' => 'run processes silently; without logging any output',
    'action'      => 'StoreInt',
    'default'     => 1,
  ]
);

$parser->addOption(
  'name_separator',
  [
    'long_name'   => '--name-separator',
    'description' => 'specifies the character to use to split process names',
    'action'      => 'StoreString',
    'default'     => ',',
  ]
);

$parser->addArgument(
  'processes',
  [
    'description' => 'commands to execute concurrently',
    'optional'    => false,
  ]
);

// define writable stream
$writable = new WritableResourceStream(STDOUT);

// process commandline artifacts - arguments and options
$cmd      = toException(
  function () use ($parser) {
    return $parser->parse();
  },
  function (\Throwable $err) use ($writable) {
    $writable->write(
      \sprintf("%s\n", applyColor($err->getMessage(), 'red'))
    );

    Loop::get()->stop();

    exit();
  }
)();
