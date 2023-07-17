<?php

/**
 * TransientObservable
 * simple Transient data structure for working with Observable types
 *
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Concurrently\Proc;

use Rx\Observable;
use React\Promise\PromiseInterface;
use Chemem\Bingo\Functional\Common\Traits\TransientMutator;

class TransientObservable
{
  use TransientMutator;

  /**
   * observable
   *
   * @var Observable $observable
   */
  private $observable;

  public function __construct(Observable $observable)
  {
    $this->observable = $observable;
  }

  /**
   * fromPromise
   * creates a TransientObservable from a Promise
   *
   * fromPromise :: Promise s a -> TransientObservable a
   *
   * @param PromiseInterface $promise
   * @return TransientObservable
   * @example
   *
   * TransientObservable::fromPromise(resolve(2))
   * => object(Chemem\Concurrently\Proc\TransientObservable) {}
   */
  public static function fromPromise(PromiseInterface $promise): TransientObservable
  {
    return new static(Observable::fromPromise($promise));
  }

  /**
   * merge
   * merges one TransientObservable into another
   *
   * merge :: TransientObservable a -> TransientObservable b
   *
   * @param TransientObservable $transient
   * @return TransientObservable
   * @example
   *
   * TransientObservable::fromPromise(resolve(2))
   *   ->merge(TransientObservable::fromPromise(resolve(3)))
   * => object(Chemem\Concurrently\Proc\TransientObservable) {}
   */
  public function merge(TransientObservable $transient): TransientObservable
  {
    return $this->update(
      $this->observable->merge(
        $transient->getObservable()
      )
    );
  }

  /**
   * mergeN
   * combines multiple TransientObservable entities
   *
   * mergeN :: [TransientObservable a, TransientObservable b] -> TransientObservable c
   *
   * @param TransientObservable ...$transients
   * @return TransientObservable
   * @example
   *
   * TransientObservable::fromPromise(resolve(2))
   *   ->mergeN(
   *     TransientObservable::fromPromise(resolve(3)),
   *     TransientObservable::fromPromise(resolve(4)),
   *   )
   * => object(Chemem\Concurrently\Proc\TransientObservable) {}
   */
  public function mergeN(TransientObservable ...$transients): TransientObservable
  {
    return $this->merge(
      \array_shift($transients)
        ->triggerMutation(
          function ($transient) use ($transients) {
            for ($idx = 0; $idx < \count($transients); ++$idx) {
              $transient->merge($transients[$idx]);
            }

            return $transient;
          }
        )
    );
  }

  /**
   * getObservable
   * unwraps a TransientObservable object (outputs an Observable)
   *
   * getObservable :: Object
   *
   * @return Observable
   * @example
   *
   * TransientObservable::fromPromise(resolve(2))->getObservable()
   * => object(Rx\Observable) {}
   */
  public function getObservable(): Observable
  {
    return $this->observable;
  }

  /**
   * update
   * performs conditional state mutation (updates Observable conditionally)
   *
   * @param Observable $observable
   * @return TransientObservable
   */
  private function update(Observable $observable): TransientObservable
  {
    if ($this->isMutable()) {
      $this->observable = $observable;

      return $this;
    }

    return new static($observable);
  }
}
