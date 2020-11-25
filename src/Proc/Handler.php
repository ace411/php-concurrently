<?php

/**
 * Handler
 * asynchronous process handler
 * 
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Concurrently\Proc;

use \React\{
  Promise\Stream,
  ChildProcess\Process,
  EventLoop\LoopInterface,
  Promise\PromiseInterface,
  Stream\WritableResourceStream,
};
use Rx\Observable;
use \Clue\React\Mq\Queue;
use \Chemem\Bingo\Functional\Algorithms as f;
use function Chemem\Concurrently\Console\applyTextColor;

class Handler
{
  /**
   * event-loop instance
   *
   * @var LoopInterface $loop
   */
  private $loop;

  public function __construct(LoopInterface $loop)
  {
    $this->loop = $loop;
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
   * // => object(Chemem\Concurrently\Proc\Handler) {}
   */
  public static function for(LoopInterface $loop): Handler
  {
    return new static($loop);
  }

  /**
   * asyncProcess
   * runs a process asynchronously and subsumes its result in a Promise
   * 
   * asyncProcess :: a -> Bool -> Promise s a 
   * 
   * @param string $cmd
   * @param boolean $silent
   * @return PromiseInterface
   * @example
   * 
   * Handler::for($loop)->asyncProcess('ls', false)
   * // => object(React\Promise\Promise) {}
   */
  public function asyncProcess(
    string $cmd,
    bool $silent    = false,
    bool $color     = false,
    string $prefix  = ''
  ): PromiseInterface {
    $process = new Process($cmd);
    $process->start($this->loop);

    return Stream\buffer(
      $process->stdout->on('data', function (string $chunk) use (
        $silent,
        $color,
        $prefix
      ) {
        $printable = !$color ? $chunk : applyTextColor($chunk, 'none');

        if (!$silent) {
          $writable = new WritableResourceStream(STDOUT, $this->loop);
          // print process name above its output
          $writable->write(
            f\concat(
              PHP_EOL,
              '',
              applyTextColor($prefix, !$color ? 'none' : 'dark_gray'),
              ''
            )
          );
          $writable->write($printable);
        }

        return $printable;
      })
    );
  }

  /**
   * multipleProcesses
   * executes multiple processes concurrently and subsumes the composite result in an observable
   *
   * multipleProcesses :: [a] -> Int -> Bool -> TransientObservable a 
   * 
   * @param array $cmds
   * @param integer|null $maxProcesses
   * @param boolean $silent
   * @return TransientObservable
   * @example
   * 
   * // Handler::for($loop)->multipleProcesses(['ls', 'cat file.txt'], 2, false)
   * // => object(Chemem\Concurrently\Proc\TransientObservable) {}
   */
  public function multipleProcesses(
    array $cmds,
    ?int $maxProcesses,
    bool $silent  = false,
    bool $color   = false
  ): TransientObservable {
    $cmdCount = \count($cmds);
    $queue    = new Queue(
      $cmdCount,
      $maxProcesses <= $cmdCount ? $cmdCount + 1 : $maxProcesses,
      function (string $cmd) use ($silent, $color) {
        $proc = \ltrim($cmd, ' ');

        return $this->asyncProcess(
          $cmd,
          $silent,
          $color,
          f\concat(' ', '$', $cmd)
        );
      }
    );

    return $this->observableFromArray(
      f\map(function (string $cmd) use ($queue) {
        return TransientObservable::fromPromise($queue($cmd));
      }, $cmds)
    );
  }

  /**
   * observableFromArray
   * creates a TransientObservable instance from an array containing promises
   * 
   * observableFromArray :: [Promise s a] -> TransientObservable a
   * 
   * @param array $promises
   * @return TransientObservable
   */
  private function observableFromArray(array $promises): TransientObservable
  {
    return f\head($promises)->mergeN(...f\tail($promises));
  }
}
