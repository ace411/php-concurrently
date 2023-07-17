<?php

declare(strict_types=1);

namespace Chemem\Concurrently\Tests\Proc;

// \error_reporting(0);

use Eris\Generator;
use Eris\TestTrait;
use Chemem\Concurrently\Proc\Handler;
use PHPUnit\Framework\TestCase;

use function React\Async\await;
use function Chemem\Bingo\Functional\toException;

class HandlerTest extends TestCase
{
  use TestTrait;

  /**
   * @test
   */
  public function asyncProcessExecutesProcessAsynchronously()
  {
    $this
      ->forAll(
        Generator\elements(
          'ls',
          'find composer.json',
          'php foo.php'
        ),
        Generator\bool(),
        Generator\elements(
          'ls',
          'find composer.json',
          'php foo.php'
        ),
        Generator\choose(1, 3)
      )
      ->then(
        function (
          string $cmd,
          bool $color,
          string $prefix,
          int $attempts
        ) {
          $process = toException(
            function ($result) use (
              $attempts,
              $cmd,
              $color,
              $prefix
            ) {
              return await(
                Handler::for($this->eventLoop())
                  ->asyncProcess($cmd, $color, $prefix, $attempts)
              );
            }
          )();

          $this->assertIsString($process);
        }
      );
  }

  /**
   * @test
   */
  public function multipleProcessesExecutesMultipleProcessesConcurrently()
  {
    $this
      ->forAll(
        Generator\tuple(
          Generator\constant('ls'),
          Generator\constant('find composer.json'),
          Generator\constant('php foo.php'),
          Generator\constant('pwd')
        ),
        Generator\choose(5, 7),
        Generator\bool(),
        Generator\choose(1, 3)
      )
      ->then(
        function (
          array $cmds,
          int $maxProcesses,
          bool $color,
          int $attempts
        ) {
          $processes = toException(
            function () use (
              $attempts,
              $cmds,
              $color,
              $maxProcesses
            ) {
              return await(
                Handler::for($this->eventLoop())
                  ->multipleProcesses($cmds, $maxProcesses, true)
                  ->getObservable()
                  ->toPromise()
              );
            }
          )();

          $this->assertIsString($processes);
        }
      );
  }
}
