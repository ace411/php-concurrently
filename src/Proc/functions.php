<?php

/**
 * Process handling functions
 *
 * @package chemem/php-concurrently
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Concurrently\Proc;

use AlecRabbit\Snake\Contracts\Color;
use AlecRabbit\Snake\Spinner;
use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Stream\WritableStreamInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Observer\CallbackObserver;

use function Chemem\Bingo\Functional\compose;
use function Chemem\Bingo\Functional\partial;
use function Chemem\Bingo\Functional\partialRight;

use const Chemem\Bingo\Functional\pluck;

const execute = __NAMESPACE__ . '\\execute';

/**
 * execute
 * executes processes concurrently
 *
 * execute :: Array -> String -> Object -> Object -> Object
 *
 * @param array $options
 * @param string $processes
 * @param WritableStreamInterface $writable
 * @param LoopInterface|null $loop
 * @return object
 */
function execute(
  array $options,
  string $processes,
  WritableStreamInterface $writable,
  LoopInterface $loop = null
): CallbackDisposable {
  $opt    = partial(pluck, $options);
  $print  = partialRight(
    printAsync,
    $loop,
    !$opt('no_spinner'),
    !$opt('no_color'),
    !$opt('silent'),
    $writable
  );

  return Handler::for($loop)
    ->multipleProcesses(
      \explode($opt('name_separator'), $processes),
      $opt('max_processes'),
      !$opt('no_color'),
      $opt('retries')
    )
    ->getObservable()
    ->subscribe(
      new CallbackObserver(
        function ($success) use ($print) {
          $print($success);
        },
        function ($err) use ($print) {
          compose(
            partialRight(applyColor, 'red'),
            $print
          )($err->getMessage());
        },
        function () use ($print) {
          $print('');
        }
      )
    );
}

const applyColor = __NAMESPACE__ . '\\applyColor';

/**
 * applyColor
 * creates colored text apt for display in a console
 *
 * applyColor :: String -> String -> String
 *
 * @param string $text
 * @param string $color
 * @return string
 */
function applyColor(string $text, string $color = 'none'): string
{
  return (new ConsoleColor())
    ->apply($color, $text);
}

const printAsync = __NAMESPACE__ . '\\printAsync';

/**
 * printAsync
 * logs customized console-bound output to STDOUT via writable stream
 *
 * printAsync :: String -> Object -> Bool -> Bool -> Bool -> Object -> Object
 *
 * @param string $message
 * @param WritableStreamInterface $writable
 * @param boolean $silent
 * @param boolean $color
 * @param boolean $spinner
 * @param LoopInterface|null $loop
 * @return WritableStreamInterface
 */
function printAsync(
  string $message,
  WritableStreamInterface $writable,
  bool $silent        = false,
  bool $color         = false,
  bool $spinner       = true,
  LoopInterface $loop = null
): WritableStreamInterface {
  if ($silent) {
    $writable->write($message);
  }

  if ($spinner) {
    $spinner = new Spinner(
      $color ? Color::COLOR_256 : Color::NO_COLOR
    );

    if (\is_null($loop)) {
      $loop = Loop::get();
    }

    $loop->addPeriodicTimer(
      $spinner->interval(),
      function () use ($spinner) {
        $spinner->spin();
      }
    );

    // terminate spinner
    $spinner->end();
  }

  return $writable;
}
