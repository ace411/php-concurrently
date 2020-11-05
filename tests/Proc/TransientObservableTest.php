<?php

declare(strict_types=1);

namespace Chemem\Concurrently\Tests\Proc;

\error_reporting(0);

use \Rx\Observable;
use \Eris\Generator;
use Chemem\{
  Bingo\Functional\Algorithms as f,
  Concurrently\Proc\TransientObservable as Transient,
};
use function \React\Promise\{resolve, reject};

class TransientObservableTest extends \seregazhuk\React\PromiseTesting\TestCase
{
  use \Eris\TestTrait;

  /**
   * @test
   */
  public function fromPromiseCreatesTransientObservableFromPromise()
  {
    $this
      ->forAll(
        Generator\subset([2, 3.2, 'foo', 'bar', 'baz']),
      )
      ->then(function ($val) {
        $transient = Transient::fromPromise(resolve($val));

        $this->assertInstanceOf(Transient::class, $transient);
      });
  }

  /**
   * @test
   */
  public function getObservableUnwrapsTransientObservableClassRevealingObservable()
  {
    $this
      ->forAll(
        Generator\subset([2, 3.2, 'foo', 'bar', 'baz']),
      )
      ->then(function ($val) {
        $transient = Transient::fromPromise(resolve($val));

        $this->assertInstanceOf(Observable::class, $transient->getObservable());
      });
  }

  /**
   * @test
   */
  public function mergeMergesOneTransientObservableIntoAnother()
  {
    $this
      ->forAll(
        Generator\subset([2, 3.2, 'foo', 'bar', 'baz']),
        Generator\subset([4, 'foo-bar', null, 'mixer', (object) \range(1, 3)]),
      )
      ->then(function (array $fst, array $snd) {
        $success = Transient::fromPromise(resolve($fst))
          ->merge(Transient::fromPromise(resolve($snd)));

        $failure = Transient::fromPromise(reject($fst))
          ->merge(Transient::fromPromise(reject($snd)));

        $this->assertInstanceOf(Transient::class, $success);
        $this->assertPromiseFulfills(
          $success
            ->getObservable()
            ->toPromise(),
        );
        $this->assertTrueAboutPromise(
          $success
            ->getObservable()
            ->toPromise(),
          'is_array',
        );

        $this->assertInstanceOf(Transient::class, $failure);
        $this->assertPromiseRejects(
          $failure
            ->getObservable()
            ->toPromise(),
        );
      });
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
          Generator\choose(1, 4),
        ),
        Generator\tuple(
          Generator\elements('baz', 'bat'),
          Generator\choose(3, 8),
        ),
      )
      ->then(function (array $fst, array $snd) {
        // create a single TransientObservable from multiple TransientObservables
        $combine    = function (array $transients) {
          return f\head($transients)->mergeN(...f\tail($transients));
        };

        $transient  = function (array $data, $resolve = true) {
          return f\map(function ($entry) use ($resolve) {
            return Transient::fromPromise(
              $resolve ? resolve($data) : reject($data),
            );
          }, $data);
        };

        $success    = $combine($transient($fst));
        $failure    = $combine($transient($snd, false));

        $this->assertInstanceOf(Transient::class, $success);
        $this->assertPromiseFulfills(
          $success
            ->getObservable()
            ->toPromise(),
        );

        $this->assertInstanceOf(Transient::class, $failure);
        $this->assertPromiseRejects(
          $failure
            ->getObservable()
            ->toPromise(),
        );
      });
  }
}
