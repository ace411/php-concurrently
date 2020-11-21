<?php

declare(strict_types=1);

namespace Chemem\Concurrently\Tests\Proc;

\error_reporting(0);

use \Eris\Generator;
use Chemem\Concurrently\Proc\Handler;

class HandlerTest extends \seregazhuk\React\PromiseTesting\TestCase
{
  use \Eris\TestTrait;
  
  /**
   * @test
   */
  public function asyncProcessExecutesProcessAsynchronously()
  {
    $this
      ->forAll(
        Generator\elements('ls', 'find composer.json', 'php foo.php')
      )
      ->then(function (string $cmd) {
        $proc = Handler::for($this->eventLoop())
          // set logging to true to preempt priting to STDOUT
          ->asyncProcess($cmd, true);

        $this->assertPromiseFulfills($proc);
        $this->assertTrueAboutPromise($proc, 'is_string');
      });
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
        Generator\choose(5, 7)
      )
      ->then(function (array $cmds, int $maxProcesses) {
        $processes = Handler::for($this->eventLoop())
          // set silent to true to preempt priting to STDOUT
          ->multipleProcesses($cmds, $maxProcesses, true)
          ->getObservable()
          ->toPromise();

        $this->assertPromiseFulfills($processes);
        $this->assertTrueAboutPromise($processes, 'is_string');
      });
  }
}
