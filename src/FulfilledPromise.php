<?php
namespace Gt\Promise;

use Throwable;
use Http\Promise\Promise as HttpPromiseInterface;

class FulfilledPromise implements PromiseInterface {
	use Resolvable;
	use Waitable;

	/** @var mixed */
	private $value;
	private string $state;

	/** @param ?mixed $promiseOrValue */
	public function __construct($promiseOrValue = null) {
		if($promiseOrValue instanceof PromiseInterface) {
			throw new FulfilledValueNotConcreteException(get_class($promiseOrValue));
		}

		$this->value = $promiseOrValue;
	}

	public function then(
		callable $onFulfilled = null,
		callable $onRejected = null
	):PromiseInterface {
		if(is_null($onFulfilled)) {
			return $this;
		}

		return new Promise(
			function(callable $resolve, callable $reject)
			use ($onFulfilled):void {
//				try {
					$resolve($onFulfilled($this->value));
//				}
//				catch(Throwable $reason) {
//					$reject($reason);
//				}
			}
		);
	}

	public function complete(
		callable $onFulfilled = null,
		callable $onRejected = null
	):void {
		if(is_null($onFulfilled)) {
			return;
		}

		$result = $onFulfilled($this->value);

		if($result instanceof PromiseInterface) {
			$result->complete();
		}
	}

	public function catch(callable $onRejected):PromiseInterface {
		return $this;
	}

	public function finally(
		callable $onFulfilledOrRejected
	):PromiseInterface {
		return $this->then(
			function($value)
			use ($onFulfilledOrRejected):PromiseInterface {
				return $this->resolve($onFulfilledOrRejected())
					->then(function() use ($value) {
						return $value;
					});
			}
		);
	}

	public function getState():string {
		return HttpPromiseInterface::FULFILLED;
	}
}