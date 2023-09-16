<?php

declare(strict_types=1);

namespace Chemem\Concurrently\Tests\Proc;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Stream\WritableResourceStream;
use Rx\Disposable\CallbackDisposable;

use function Chemem\Bingo\Functional\toException;
use function Chemem\Concurrently\Proc\execute;
use function Chemem\Concurrently\Proc\printAsync;

class FunctionsTest extends TestCase
{
  use TestTrait;

  private static $file;
  private static $writable;

  public static function setupBeforeClass(): void
  {
    self::$file     = __DIR__ . '/file.log';
    self::$writable = new WritableResourceStream(
      \fopen(self::$file, 'a')
    );
  }

  public static function tearDownAfterClass(): void
  {
    Loop::get()->stop();

    \unlink(self::$file);
  }

  /**
   * @test
   */
  public function printAsyncLogsCustomizedConsoleBoundOutputToSTDOUTViaWritableStream()
  {
    $this
      ->forAll(
        Generator\elements('foo', 'bar', \sprintf("%s\n%s", 'foo', 'bar')),
        Generator\bool(),
        Generator\bool()
      )
      ->then(
        function (
          string $message,
          bool $silent,
          bool $color
        ) {
          $print    = toException(
            function () use ($color, $message, $silent) {
              return printAsync(
                $message,
                self::$writable,
                $silent,
                $color,
                false
              );
            }
          );

          $result = $print();

          $this->assertTrue(
            $result instanceof WritableResourceStream ||
            \is_string($result)
          );
        }
      );
  }

  /**
   * @test
   */
  public function executeExecutesProcessesConcurrently()
  {
    $this
      ->forAll(
        Generator\associative(
          [
            'name_separator'  => Generator\elements(',', ':'),
            'max_processes'   => Generator\choose(4, 9),
            'no_spinner'      => Generator\constant(true),
            'no_color'        => Generator\bool(),
            'retries'         => Generator\choose(1, 3),
            'silent'          => Generator\bool(),
          ]
        ),
        Generator\elements('ls,pwd', 'pwd:ls -a')
      )
      ->then(
        function (array $options, string $processes) {
          $concurrent = toException(
            function () use ($options, $processes) {
              return execute(
                $options,
                $processes,
                self::$writable
              );
            }
          );

          $result = $concurrent();

          $this->assertTrue(
            $result instanceof CallbackDisposable ||
            \is_string($result)
          );
        }
      );
  }
}
