<?php
namespace Gt\Promise;

use Throwable;
use Http\Promise\Promise as HttpPromiseInterface;

class RejectedPromise implements PromiseInterface {
	use Resolvable;
	use Waitable;

	private Throwable $reason;
	private string $state;

	public function __construct(Throwable $reason) {
		$this->reason = $reason;
	}

	public function then(
		callable $onFulfilled = null,
		callable $onRejected = null
	):PromiseInterface {
		if(is_null($onRejected)) {
			return $this;
		}

		return new Promise(function(callable $resolve, callable $reject)
		use ($onRejected):void {
			try {
				$resolve($onRejected($this->reason));
			}
			catch(Throwable $reason) {
				$reject($reason);
			}
		});
	}

	public function complete(
		callable $onFulfilled = null,
		callable $onRejected = null
	):void {
		if(null === $onRejected) {
			throw $this->reason;
		}

		$result = $onRejected($this->reason);

		if($result instanceof self) {
			throw $result->reason;
		}

		if($result instanceof PromiseInterface) {
			$result->complete();
		}
	}

	public function catch(
		callable $onRejected
	):PromiseInterface {
		return $this->then(null, $onRejected);
	}

	public function finally(
		callable $onFulfilledOrRejected
	):PromiseInterface {
		return $this->then(
			null,
			function(Throwable $reason)
			use ($onFulfilledOrRejected):PromiseInterface {
				return $this->resolve($onFulfilledOrRejected())
					->then(function() use ($reason):PromiseInterface {
						return new RejectedPromise(
							$reason
						);
					});
			}
		);
	}

	public function getState():string {
		return HttpPromiseInterface::REJECTED;
	}
}