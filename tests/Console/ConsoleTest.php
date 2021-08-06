<?php

declare(strict_types=1);

namespace Chemem\Concurrently\Tests\Console;

use Eris\Generator;
use Chemem\Concurrently\Console as c;
use Chemem\Bingo\Functional as f;
use Chemem\Bingo\Functional\Functors\Monads\IO;
use Chemem\Concurrently\Proc\TransientObservable;
use Rx\Observer;
use Rx\Observable;
use Rx\Disposable\CallbackDisposable;

class ConsoleTest extends \seregazhuk\React\PromiseTesting\TestCase
{
  use \Eris\TestTrait;

  /**
   * @test
   */
  public function applyTextColorCreatesColoredText()
  {
    $this
      ->forAll(
        Generator\suchThat(function (string $str) {
          return !empty($str) && \strlen($str) > 3;
        }, Generator\names())
      )
      ->then(function (string $str) {
        $colored = c\applyTextColor($str);

        $this->assertIsString($colored);
        $this->assertTrue((bool) \preg_match('/(' . $str . ')*/', $colored));
      });
  }

  /**
   * @test
   */
  public function printTableOutputsSQLEsqueTable()
  {
    $this
      ->forAll(
        Generator\associative([
          'help'    => Generator\constant('concurrently --help'),
          'version' => Generator\constant('concurrently --version'),
          'run'     => Generator\constant('concurrently \"ls,find composer.lock\"'),
        ])
      )
      ->then(function (array $body) {
        $table = c\printTable($body);

        $this->assertIsString($table);
        $this->assertTrue((bool) \preg_match('/(\w\W\d)*/', $table));
      });
  }

  /**
   * @test
   */
  public function parseHelpCmdOutputsHelpInfo()
  {
    $help = c\parseHelpCmd();

    $this->assertIsString($help->exec());
  }

  /**
   * @test
   */
  public function encodeConsoleArgsCreatesHashTableFromSpecifiedConsoleOperations()
  {
    $this
      ->forAll(
        Generator\elements(
          '-m=10 "ls, cat foo.txt, find composer.json"',
          '--name-separator=";" -m=4 "find composer.lock;pwd"',
          '-s "ls,cat foo.txt"',
          '--no-color "ls,cat foo.txt"',
          '--no-spinner --no-color "ls", "find composer.lock"'
        )
      )
      ->then(function (string $cmd) {
        $args = c\encodeConsoleArgs($cmd);

        $this->assertIsArray($args);
        $this->assertTrue(
          f\keysExist(
            $args,
            'color',
            'silent',
            'spinner',
            'processes',
            'max_processes',
            'name_separator'
          )
        );
      });
  }

  /**
   * @test
   * @eris-repeat 5
   */
  public function executeProcessesConcurrentlyExecutesMultipleProcesses()
  {
    $this
      ->forAll(
        // generate list of arguments from arbitrary directives
        Generator\map(
          c\encodeConsoleArgs,
          Generator\elements(
            '-m=10 "ls, cat foo.txt, find composer.json"',
            '--name-separator=";" -m=4 "find composer.lock;pwd"',
            '-s "ls,cat foo.txt"',
            '--no-color "ls,cat foo.txt"',
            '--no-spinner --no-color "ls", "find composer.lock"'
          )
        )
      )
      ->then(function (array $args) {
        $pluck      = f\partial(f\pluck, $args);
        $exec       = c\executeProcesses($this->eventLoop(), $args);
        $printable  = $exec->bind(
          f\partialRight(
            c\printProcessResult,
            $pluck('color'),
            $pluck('silent'),
            $this->eventLoop()
          )
        );

        $this->assertInstanceOf(IO::class, $exec);
        $this->assertInstanceOf(TransientObservable::class, $exec->exec());
        $this->assertInstanceOf(IO::class, $printable);
        $this->assertInstanceOf(CallbackDisposable::class, $printable->exec());
      });
  }
}
