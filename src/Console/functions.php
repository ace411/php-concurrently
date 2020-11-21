<?php

/**
 * Console functions
 * 
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Concurrently\Console;

use \Chemem\Bingo\Functional\{
  Algorithms as f,
  Functors\Either,
  Functors\Monads\IO,
  Functors\Monads as m,
  PatternMatching as p,
};
use Chemem\Concurrently\Proc\{
  Handler,
  TransientObservable,
};
use \AlecRabbit\Snake\{
  Spinner,
  Contracts\Color,
};
use \Mmarica\DisplayTable;
use \Rx\Observer\CallbackObserver;
use \React\EventLoop\LoopInterface;
use \PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;

/**
 * parse
 * parse Console input
 *
 * parse :: String -> IO ()
 * 
 * @param string $input
 * @return IO
 * @example
 * 
 * parse('--help')
 * // => object(Chemem\Bingo\Functional\Functors\Monads\IO) {}
 */
function parse(LoopInterface $loop, string $input): IO
{
  // partially compose putStr cmd directive
  $print    = f\partial(m\mcompose, IO\putStr);
  // IO-composed version print functions
  $version  = $print(function () {
    return IO\IO(VERSION);
  });
  // IO-composed help info print functions
  $help     = $print(parseHelpCmd);
  
  $matches  = p\match([
    '(x:_)' => function (string $opt) use ($help, $version) {
      return p\patternMatch([
        // concurrently -h
        '"-h"'        => function () use ($help) {
          return $help(IO\IO(null));
        },
        // concurrently -v
        '"-v"'        => function () use ($version) {
          return $version(IO\IO(null));
        },
        // concurrently --help
        '"--help"'    => function () use ($help) {
          return $help(IO\IO(null));
        },
        // concurrently --version
        '"--version"' => function () use ($version) {
          return $version(IO\IO(null));
        },
        // concurrently
        '_'           => function () use ($help) {
          return $help(IO\IO(null));
        },
      ], $opt);
    },
    // concurrently [options] [arguments]
    '_'     => function () use ($loop, $input) {
      $args = encodeConsoleArgs($input);
      [
        'spinner' => $spinner,
        'silent'  => $silent,
        'color'   => $color,
      ]     = $args;
      $res  = m\mcompose(
        f\partialRight(
          printProcessResult,
          $spinner,
          $color,
          $silent,
          $loop
        ),
        f\partial(executeProcesses, $loop)
      );

      return $res(IO\IO($args));
    },
  ]);

  return $matches(\explode(' ', $input));
}

const parse = __NAMESPACE__ . '\\parse';

/**
 * printTable
 * prints a SQL-esque table
 *
 * printTable :: String -> [a] -> String
 * 
 * @param string $header
 * @param array $body
 * @param integer $vpadding
 * @return string
 * @example
 * 
 * printTable(['Print help' => 'concurrently --help'], 1.2, 1)
 * // => Print help           concurrently --help
 */
function printTable(
  array $body,
  float $vpadding = 1.0,
  float $hpadding = 1.0
): string {
  return DisplayTable::create()
    ->dataRows(f\toPairs($body)) // key-value pairs
    ->toText()
    ->vPadding($vpadding)
    ->hPadding($hpadding)
    ->noBorder()
    ->generate();
}

const printTable = __NAMESPACE__ . '\\printTable';

/**
 * applyTextColor
 * creates colored text
 * 
 * applyTextColor :: String -> String -> String
 * 
 * @param string $text
 * @param string $color
 * @return string
 * @example
 * 
 * applyTextColor('foo', 'cyan')
 * // => \033[36foom
 */
function applyTextColor(string $text, string $color = 'none'): string
{
  return (new ConsoleColor)->apply($color, $text);
}

const applyTextColor = __NAMESPACE__ . '\\applyTextColor';

/**
 * parseHelpCmd
 * prints help command information
 *
 * parseHelpCmd :: IO ()
 * 
 * @return IO
 * @example
 * 
 * parseHelpCmd()
 * // => object(Chemem\Bingo\Functional\Functors\Monads\IO) {}
 */
function parseHelpCmd(): IO
{
  return IO\IO(
    f\concat(
      PHP_EOL,
      applyTextColor(HEADER, 'cyan'),
      'General',
      printTable(HELP_INFO, 1.2),
      'Examples',
      printTable(EXAMPLES, 1.2)
    )
  );
}

const parseHelpCmd = __NAMESPACE__ . '\\parseHelpCmd';

/**
 * encodeConsoleArgs
 * losslessly converts console arguments to more descriptive key-value pairs
 *
 * encodeConsoleArgs :: String -> Array
 * 
 * @param string $input
 * @return array
 * @example
 * 
 * encodeConsoleArgs('--silent --name-separator="|" ls | composer.lock')
 * // => array(4) {["silent"]=>bool(true),["name_separator"]=>string("|"),["max_processes"] ...}
 */
function encodeConsoleArgs(string $input): array
{
  // creates an associative array
  $assoc = function (string $key, $val): array {
    return [$key => $val];
  };

  return f\fold(function (array $acc, string $directive) use ($assoc) {
    $opts = p\patternMatch([
      // -m=<val> -> ['max_processes' => val]
      '["-m", max]'                     => function (string $max) use ($assoc) {
        return $assoc('max_processes', \is_numeric($max) ? (int) $max : null);
      },
      // --max-processes=<val> -> ['max_processes' => val]
      '["--max-processes", max]'        => function (string $max) use ($assoc) {
        return $assoc('max_processes', \is_numeric($max) ? (int) $max : null);
      },
      // --name-separator="<separator>" -> ['name_separator' => '<separator>']
      '["--name-separator", separator]' => function (string $separator) use ($assoc) {
        return $assoc('name_separator', \str_replace('"', '', $separator));
      },
      // --silent -> ['silent' => true]
      '["--silent"]'                    => function () use ($assoc) {
        return $assoc('silent', true);
      },
      // -s -> ['silent' => true]
      '["-s"]'                          => function () use ($assoc) {
        return $assoc('silent', true);
      },
      // --no-color -> ['color' => false]
      '["--no-color"]'                  => function () use ($assoc) {
        return $assoc('color', false);
      },
      // --no-spinner -> ['spinner' => false]
      '["--no-spinner"]'                => function () use ($assoc) {
        return $assoc('spinner', false);
      },
      // "<proc a>, <proc b>" -> ['processes' => '<proc a>,<proc b>']
      '_'                               => function () use ($directive, $acc) {
        $trim = f\partialRight('ltrim', ' ');
        
        return [
          'processes' => f\concat(' ', $trim($acc['processes']), $trim($directive)),
        ];
      },
    ], \explode('=', $directive));

    return f\extend($acc, $opts);
  }, \explode(' ', $input), DEFAULT_PROC_OPTS);
}

const encodeConsoleArgs = __NAMESPACE__ . '\\encodeConsoleArgs';

/**
 * executeProcesses
 * executes processes concurrently
 * 
 * executeProcesses :: Object -> [a] -> IO () 
 *
 * @param LoopInterface $loop
 * @param array $args
 * @return IO
 * 
 * executeProcesses($loop, DEFAULT_PROC_OPTS)
 * // => object(Chemem\Bingo\Functional\Functors\Monads\IO) {}
 */
function executeProcesses(LoopInterface $loop, array $args = DEFAULT_PROC_OPTS): IO
{
  return IO\IO(function () use ($loop, $args) {
    // perform case-analysis on script arguments
    return Either\either(
      // print error message in the event of fault detection
      function (string $msg) use ($loop) {
        // create TransientObservable from `echo ...` process
        return TransientObservable::fromPromise(
          Handler::for($loop)
            ->asyncProcess(f\concat(' ', 'echo', $msg))
        );
      },
      // run processes normally in the event of success
      function (array $args) use ($loop) {
        $pluck = f\partial(f\pluck, $args);

        return Handler::for($loop)
          ->multipleProcesses(
            // separate processes by name separator
            \explode($pluck('name_separator'), $pluck('processes')),
            $pluck('max_processes'),
            $pluck('silent'),
            $pluck('color')
          );
      },
      // perform error checks
      Either\Either::right($args)
        // check if process list is an empty string
        ->filter(function ($args) {
          return !empty($args['processes']);
        }, 'Empty process list')
        // check if separator does not exist in process definition
        ->filter(function (array $args) {
          $pluck = f\partial(f\pluck, $args);

          return \preg_match(
            f\concat('', '/(\\', $pluck('name_separator'), ')+/'),
            $pluck('processes')
          );
        }, 'Separator mismatch')
    );
  });
}

const executeProcesses = __NAMESPACE__ . '\\executeProcesses';

/**
 * printProcessResult
 * prints composite result of a TransientObservable
 *
 * printProcessResult :: TransientObservable a -> IO ()
 * 
 * @param TransientObservable $transient
 * @return IO
 * @example
 * 
 * printProcessResult(TransientObservable::fromPromise(resolve(2)))
 * // => object(Chemem\Bingo\Functional\Functors\Monads\IO) {}
 */
function printProcessResult(
  TransientObservable $transient,
  LoopInterface $loop,
  bool $silent  = false,
  bool $color   = true,
  bool $spinner = true
): IO {
  $print = function (string $msg) use ($silent, $loop, $color, $spinner) {
    $print = IO\putStr($msg);

    // print spinner if the option is set to true
    if ($spinner) {
      printSpinner($loop, $color)->exec();
    }

    // preempt double printing to the screen
    return $silent || !empty($msg) ? $print : $print->exec();
  };

  return IO\IO(function () use ($transient, $print) {
    return $transient
      ->getObservable()
      ->subscribe(
        new CallbackObserver(
          function (string $success) use ($print) {
            return $print($success);
          },
          function (\Throwable $err) use ($print) {
            return $print($err->getMessage());
          },
          function () use ($print) {
            return $print('');
          }
        )
      );
  });
}

const printProcessResult = __NAMESPACE__ . '\\printProcessResult';

/**
 * printSpinner
 * prints Spinner artifact to console
 * 
 * printSpinner :: Object -> Bool -> IO () 
 *
 * @param LoopInterface $loop
 * @param bool $color
 * @return IO
 * 
 * printSpinner($loop, true)
 * // => object(Chemem\Bingo\Functional\Functors\Monads\IO) {} 
 */
function printSpinner(LoopInterface $loop, bool $color = true): IO
{
  return IO\IO(function () use ($loop, $color) {
    $spinner = new Spinner($color ? Color::COLOR_256 : Color::NO_COLOR);
    
    return $loop->addPeriodicTimer(
      $spinner->interval(),
      function () use ($spinner) {
        $spinner->spin();
      }
    );
  });
}

const printSpinner = __NAMESPACE__ . '\\printSpinner';
