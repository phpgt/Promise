<?php
namespace Gt\Promise;

use Throwable;

class Promise implements PromiseInterface {
	use Resolvable;

	/** @var mixed */
	private $result;
	/** @var callable[] */
	private array $handlers;

	/**
	 * @param callable $executor A function to be executed by the
	 * constructor, during the process of constructing the Promise. The
	 * executor is custom code that ties an outcome to a promise. You,
	 * the programmer, write the executor. The signature of this function is
	 * expected to be:
	 * function(callable $resolutionFunc, callable $rejectionFunc):void {}
	 */
	public function __construct(callable $executor) {
		$this->handlers = [];
		$this->call($executor);
	}

	public function then(
		callable $onFulfilled = null,
		callable $onRejected = null
	):PromiseInterface {
		if(!is_null($this->result)) {
			return $this->result->then(
				$onFulfilled,
				$onRejected
			);
		}

		return new self($this->resolver($onFulfilled, $onRejected));
	}

	public function catch(
		callable $onRejected
	):PromiseInterface {
		return $this->then(
			null,
			fn(Throwable $reason):PromiseInterface => $onRejected($reason)
		);
	}

	public function finally(
		callable $onFulfilledOrRejected
	):PromiseInterface {
		return $this->then(
			fn($value) => $this->resolve($onFulfilledOrRejected())->then(fn() => $value),
			fn(Throwable $reason) => $this->resolve($onFulfilledOrRejected())->then(fn() => new RejectedPromise($reason))
		);
	}

	public function complete(
		callable $onFulfilled = null,
		callable $onRejected = null
	):void {
		if(!is_null($this->result)) {
			$this->result->complete($onFulfilled, $onRejected);
			return;
		}

		array_push(
			$this->handlers,
			function(PromiseInterface $promise) use ($onFulfilled, $onRejected) {
				$promise->complete($onFulfilled, $onRejected);
			}
		);
	}

	private function resolver(
		callable $onFulfilled = null,
		callable $onRejected = null
	):callable {
		return function(callable $resolve, callable $reject)
		use ($onFulfilled, $onRejected):void {
			array_push(
				$this->handlers,
				function(PromiseInterface $promise) use ($onFulfilled, $onRejected, $resolve, $reject):void {
					$promise->then($onFulfilled, $onRejected)
						->complete($resolve, $reject);
				}
			);
		};
	}

	private function reject(Throwable $reason):void {
		if(!is_null($this->result)) {
			return;
		}

		$this->settle(new RejectedPromise($reason));
	}

	private function settle(PromiseInterface $result):void {
		$result = $this->unwrap($result);

		if($result === $this) {
			$result = new RejectedPromise(
				new PromiseException("A Promise must be settled with a concrete value, not another Promise.")
			);
		}

		$handlers = $this->handlers;

		$this->handlers = [];
		$this->result = $result;

		foreach($handlers as $handler) {
			call_user_func($handler, $result);
		}
	}

	/**
	 * Obtains a reference to the ultimate promise in the chain.
	 */
	private function unwrap(PromiseInterface $promise):PromiseInterface {
		while($promise instanceof self
		&& !is_null($promise->result)) {
			$promise = $promise->result;
		}

		return $promise;
	}

	private function call(callable $callback):void {
		try {
			call_user_func(
				$callback,
				/** @param mixed $value */
				function($value = null) {
					$this->settle($this->resolve($value));
				},
				function(Throwable $reason) {
					$this->reject($reason);
				}
			);
		}
		catch(Throwable $reason) {
			$this->reject($reason);
		}
	}
}