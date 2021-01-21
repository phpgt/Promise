<?php
namespace Gt\Promise;

use Gt\Promise\Chain\CatchChain;
use Gt\Promise\Chain\Chainable;
use Gt\Promise\Chain\FinallyChain;
use Gt\Promise\Chain\ThenChain;
use Throwable;
use Http\Promise\Promise as HttpPromiseInterface;

class Promise implements PromiseInterface, HttpPromiseInterface {
	private string $state;
	/** @var mixed */
	private $resolvedValue;
	/** @var Chainable[] */
	private array $chain;
	/** @var callable */
	private $executor;
	private ?Throwable $rejection;
	/** @var callable */
	private $waitTask;
	private float $waitTaskDelayMicroseconds;

	public function __construct(callable $executor) {
		$this->state = HttpPromiseInterface::PENDING;
		$this->chain = [];
		$this->rejection = null;
		
		$this->executor = $executor;
		$this->call();
	}

	public function then(
		callable $onFulfilled = null,
		callable $onRejected = null
	):PromiseInterface {
		if($onFulfilled) {
			array_push($this->chain, new ThenChain(
				$onFulfilled,
				$onRejected
			));
		}
		elseif($onRejected) {
			array_push($this->chain, new CatchChain(
				$onFulfilled,
				$onRejected
			));
		}

		return $this;
	}

	public function catch(
		callable $onRejected
	):PromiseInterface {
		return $this->then(null, $onRejected);
	}

	public function finally(
		callable $onFulfilledOrRejected
	):PromiseInterface {
		if($onFulfilledOrRejected instanceof Throwable) {
			array_push($this->chain, new FinallyChain(
				null,
				$onFulfilledOrRejected
			));
		}
		else {
			array_push($this->chain, new FinallyChain(
				$onFulfilledOrRejected,
				null
			));
		}

		return $this;
	}

	private function complete(
		callable $onFulfilled = null,
		callable $onRejected = null
	):void {
		if(isset($this->rejection)) {
			$this->state = HttpPromiseInterface::REJECTED;
		}
		elseif(isset($this->resolvedValue)) {
			$this->state = HttpPromiseInterface::FULFILLED;
		}

		if($onFulfilled || $onRejected) {
			$this->then($onFulfilled, $onRejected);
		}

		$this->sortChain();
		$this->handleChain();
	}

	private function sortChain():void {
		usort($this->chain, fn($a, $b)
			=> $a instanceof FinallyChain ? 1 : 0);
	}

	private function handleChain():void {
		$rejectedForwardQueue = [];
		if(!is_null($this->rejection)) {
			array_push(
				$rejectedForwardQueue,
				$this->rejection
			);
		}

		$emptyChain = empty($this->chain);
		while($then = array_shift($this->chain)) {
			try {
				if($reason = array_shift($rejectedForwardQueue)) {
					$rejectedResult = $then->callOnRejected($reason);
					if($rejectedResult instanceof Throwable) {
						array_push(
							$rejectedForwardQueue,
							$rejectedResult
						);
					}
					elseif(!is_null($rejectedResult)) {
						$this->rejection = null;
						$this->resolvedValue = $rejectedResult;
					}
				}
				else {
					$value = $then->callOnFulfilled($this->resolvedValue);
					if($value instanceof PromiseInterface) {
						$value->then(function($resolvedValue) {
							$this->resolvedValue = $resolvedValue;
							$this->complete();
						});
						break;
					}

					$this->state = HttpPromiseInterface::FULFILLED;
					if(!is_null($value)) {
						$this->resolvedValue = $value;
					}
				}
			}
			catch(Throwable $reason) {
				array_push($rejectedForwardQueue, $reason);
			}
		}

		if(!$emptyChain
		&& $reason = array_shift($rejectedForwardQueue)) {
			throw $reason;
		}
	}

	public function getState():string {
		return $this->state;
	}

	public function setWaitTask(
		callable $task,
		float $delayMicroseconds = 1_000
	):void {
		$this->waitTask = $task;
		$this->waitTaskDelayMicroseconds = $delayMicroseconds;
	}

	/** @param bool $unwrap */
	public function wait($unwrap = true) {
		if(!isset($this->waitTask)) {
			throw new PromiseWaitTaskNotSetException();
		}

		while($this->getState() === HttpPromiseInterface::PENDING) {
			call_user_func($this->waitTask);
			usleep($this->waitTaskDelayMicroseconds);
		}

		$this->complete();

		if($unwrap) {
			$resolvedValue = $this->resolvedValue;
			$this->then(function($value) use(&$resolvedValue):void {
				$resolvedValue = $value;
			});

			return $resolvedValue;
		}

		return null;
	}

	private function call():void {
		call_user_func(
			$this->executor,
			/** @param mixed $value */
			function($value = null) {
				$this->resolve($value);
			},
			function(Throwable $reason) {
				$this->reject($reason);
			},
			function() {
				$this->complete();
			}
		);
	}

	/** @param mixed $value */
	private function resolve($value):void {
		if($value instanceof PromiseInterface) {
			$this->rejection = new PromiseResolvedWithAnotherPromiseException();
			return;
		}

		$this->resolvedValue = $value;
		$this->state = HttpPromiseInterface::FULFILLED;
	}

	private function reject(Throwable $reason):void {
		$this->rejection = $reason;
		$this->state = HttpPromiseInterface::REJECTED;
	}
}