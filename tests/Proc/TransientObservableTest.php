<?php

declare(strict_types=1);

namespace Chemem\Concurrently\Tests\Proc;

use Rx\Observable;
use Eris\Generator;
use Eris\TestTrait;
use Chemem\Concurrently\Proc\TransientObservable as Transient;
use PHPUnit\Framework\TestCase;

use function Chemem\Bingo\Functional\compose;
use function Chemem\Bingo\Functional\head;
use function Chemem\Bingo\Functional\map;
use function Chemem\Bingo\Functional\tail;
use function Chemem\Bingo\Functional\toException;
use function React\Async\await;
use function React\Promise\resolve;
use function React\Promise\reject;

class TransientObservableTest extends TestCase
{
  use TestTrait;

  /**
   * @test
   */
  public function fromPromiseCreatesTransientObservableFromPromise()
  {
    $this
      ->forAll(
        Generator\subset([2, 3.2, 'foo', 'bar', 'baz'])
      )
      ->then(
        function ($val) {
          $transient = Transient::fromPromise(resolve($val));

          $this->assertInstanceOf(Transient::class, $transient);
        }
      );
  }

  /**
   * @test
   */
  public function getObservableUnwrapsTransientObservableClassRevealingObservable()
  {
    $this
      ->forAll(
        Generator\subset([2, 3.2, 'foo', 'bar', 'baz'])
      )
      ->then(
        function ($val) {
          $transient = Transient::fromPromise(resolve($val));

          $this->assertInstanceOf(
            Observable::class,
            $transient->getObservable()
          );
        }
      );
  }

  /**
   * @test
   */
  public function mergeMergesOneTransientObservableIntoAnother()
  {
    $this
      ->forAll(
        Generator\subset(['foo', 'bar', 'baz']),
        Generator\subset(
          [
            'Some error',
            'Another error',
            'Yet another error',
          ]
        )
      )
      ->then(
        function (array $success, array $failure) {
          $result = toException(
            function (bool $resolve = true) use ($success, $failure) {
              return await(
                $resolve ?
                  Transient::fromPromise(resolve($success))
                    ->merge(Transient::fromPromise(resolve($failure)))
                    ->getObservable()
                    ->toPromise() :
                  Transient::fromPromise(reject(new \Exception($success)))
                    ->merge(
                      Transient::fromPromise(
                        reject(new \Exception($failure))
                      )
                    )
                    ->getObservable()
                    ->toPromise()
              );
            }
          );

          $this->assertIsArray($result());
          $this->assertIsString($result(false));
        }
      );
  }

  /**
   * @test
   */
  public function mergeNCombinesMultipleTransientObservableObjects()
  {
    $this
      ->forAll(
        Generator\tuple(
          Generator\elements('foo', 'bar'),
          Generator\elements('Some error', 'Another error'),
          Generator\elements('fooz', 'baz')
        )
      )
      ->then(
        function (array $inputs) {
          $result = toException(
            function (bool $resolve = true) use ($inputs) {
              $transients = Transient::fromPromise(
                $resolve ?
                  resolve(head($inputs)) :
                  reject(new \Exception(head($inputs)))
              )
                ->mergeN(
                  ...map(
                    function (string $input) use ($resolve) {
                      return Transient::fromPromise(
                        $resolve ?
                          resolve($input) :
                          reject(new \Exception($input))
                      );
                    },
                    tail($inputs)
                  )
                );

              return await(
                $transients
                  ->getObservable()
                  ->toPromise()
              );
            }
          );

          $this->assertIsString($result());
          $this->assertIsString($result(false));
        }
      );
  }
}
