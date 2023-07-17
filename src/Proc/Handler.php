<?php

/**
 * Handler
 * asynchronous process handler
 *
 * @package chemem/php-concurrently
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Concurrently\Proc;

use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Clue\React\Mq\Queue;
use Esroyo\BackoffAsync\Backoff;

use function Chemem\Bingo\Functional\concat;
use function Chemem\Bingo\Functional\equals;
use function Chemem\Bingo\Functional\head;
use function Chemem\Bingo\Functional\map;
use function Chemem\Bingo\Functional\tail;
use function React\Promise\resolve;

class Handler
{
  /**
   * event-loop instance
   *
   * @var LoopInterface $loop
   */
  private $loop;

  public function __construct(LoopInterface $loop = null)
  {
    $this->loop = \is_null($loop) ? Loop::get() : $loop;
  }

  /**
   * for
   * creates new Handler instance
   *
   * for :: Object -> Object
   *
   * @param LoopInterface $loop
   * @return Handler
   * @example
   *
   * Handler::for($loop)
   * => object(Chemem\Concurrently\Proc\Handler) {}
   */
  public static function for(LoopInterface $loop = null): Handler
  {
    return new static($loop);
  }

  /**
   * asyncProcess
   * runs a process asynchronously and subsumes its result in a Promise
   *
   * asyncProcess :: a -> Bool -> String -> Int -> Promise s a
   *
   * @param string $cmd
   * @param bool $color
   * @param string $prefix
   * @param int $attempts
   * @return PromiseInterface
   * @example
   *
   * Handler::for($loop)->asyncProcess('ls', false)
   * => object(React\Promise\Promise) {}
   */
  public function asyncProcess(
    string $cmd,
    bool $color     = false,
    string $prefix  = '',
    int $attempts   = 1
  ): PromiseInterface {
    // use retry strategy in backoff/async here
    $backoff  = new Backoff($attempts, 'constant');

    return $backoff
      ->run(
        function () use ($cmd) {
          return $this->proc($cmd);
        },
        $this->loop
      )
      ->otherwise(
        function (\Throwable $err) use ($color) {
          return resolve(
            applyColor(
              $err->getMessage(),
              !$color ? 'none' : 'red'
            )
          );
        }
      )
      ->then(
        function ($output) use ($color, $prefix) {
          return \sprintf(
            "%s\n%s",
            applyColor($prefix, $color ? 'cyan' : 'none'),
            $output
          );
        }
      );
  }

  /**
   * multipleProcesses
   * executes multiple processes concurrently and subsumes the composite result in an observable
   *
   * multipleProcesses :: [a] -> Int -> Bool -> TransientObservable a
   *
   * @param array $cmds
   * @param integer $maxProcesses
   * @param bool $color
   * @return TransientObservable
   * @example
   *
   * // Handler::for($loop)->multipleProcesses(['ls', 'cat file.txt'], 2, false)
   * => object(Chemem\Concurrently\Proc\TransientObservable) {}
   */
  public function multipleProcesses(
    array $cmds,
    int $maxProcesses,
    bool $color   = false,
    int $attempts = 1
  ): TransientObservable {
    $cmdCount = \count($cmds);
    $queue    = new Queue(
      $cmdCount,
      $maxProcesses <= $cmdCount ? $cmdCount + 1 : $maxProcesses,
      function (string $cmd) use ($attempts, $color) {
        $proc = \ltrim($cmd, ' ');

        return $this
          ->asyncProcess(
            $cmd,
            $color,
            concat(' ', '$', $cmd),
            $attempts
          );
      }
    );

    return $this
      ->observableFromArray(
        map(
          function (string $cmd) use ($queue) {
            return TransientObservable::fromPromise($queue($cmd));
          },
          $cmds
        )
      );
  }

  /**
   * observableFromArray
   * creates a TransientObservable instance from an array containing promises
   *
   * observableFromArray :: [Promise s a] -> TransientObservable a
   *
   * @internal
   * @param array $promises
   * @return TransientObservable
   */
  private function observableFromArray(array $promises): TransientObservable
  {
    return head($promises)
      ->mergeN(...tail($promises));
  }

  /**
   * proc
   * abstracts specialized stream-driven commandline process handling
   *
   * proc :: String -> Promise s a
   *
   * @internal
   * @param string $cmd
   * @return PromiseInterface
   */
  private function proc(string $cmd): PromiseInterface
  {
    return new Promise(
      function (callable $resolve, callable $reject) use ($cmd) {
        $proc = new Process($cmd);
        $proc->start();

        $proc->stdout->on(
          'data',
          function ($chunk) use ($resolve) {
            return $resolve($chunk);
          }
        );

        $proc->stdout->on(
          'error',
          function (\Throwable $err) use ($reject) {
            $reject($err);
          }
        );

        $proc->stdout->on(
          'end',
          function () use ($resolve) {
            $resolve(true);
          }
        );
      }
    );
  }
}
