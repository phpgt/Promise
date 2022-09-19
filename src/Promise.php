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
	private mixed $fulfilledValue;
	private ?Throwable $rejectedReason;

	/** @var Chainable[] */
	private array $chain;
	/** @var Chainable[] */
	private array $pendingChain;
	/** @var callable */
	private $executor;
	/** @var ?callable */
	private $waitTask;
	private float $waitTaskDelay;

	public function __construct(callable $executor) {
		$this->state = HttpPromiseInterface::PENDING;
		$this->chain = [];
		$this->pendingChain = [];
		$this->rejectedReason = null;

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
		if(!is_null($this->rejectedReason)) {
			array_push(
				$rejectedForwardQueue,
				$this->rejectedReason
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
						$this->rejectedReason = null;
						$this->fulfilledValue = $rejectedResult;
					}
				}
				else {
					if(isset($this->fulfilledValue)) {
						$value = $then->callOnFulfilled($this->fulfilledValue);

						if($value instanceof PromiseInterface) {
							unset($this->fulfilledValue);

							array_push($this->pendingChain, $this->chain[0] ?? null);

							$value->then(function($resolvedValue) {
								$this->fulfilledValue = $resolvedValue;
								$then = array_pop($this->pendingChain);
								if($then) {
									$then->callOnFulfilled($this->fulfilledValue);
									$this->fulfilledValue = null;
								}
								$this->complete();
							});
							break;
						}

						$this->state = HttpPromiseInterface::FULFILLED;
						if(!is_null($value)) {
							$this->fulfilledValue = $value;
						}
					}
					elseif($then instanceof FinallyChain
					&& isset($this->rejectedReason)) {
						$then->callOnFulfilled($this->rejectedReason);
					}
				}
			}
			catch(Throwable $reason) {
				array_push($rejectedForwardQueue, $reason);
			}
		}

		$reason = array_shift($rejectedForwardQueue);
		if($reason && !$emptyChain) {
			throw $reason;
		}

		if($emptyChain) {
			if($reason) {
				$this->state = HttpPromiseInterface::REJECTED;
			}
			else {
				$this->state = HttpPromiseInterface::FULFILLED;
			}
		}
	}

	public function getState():string {
		return $this->state;
	}

	public function setWaitTask(
		callable $task,
		float $delaySeconds = 0.01
	):void {
		$this->waitTask = $task;
		$this->waitTaskDelay = $delaySeconds;
	}

	/** @param bool $unwrap */
	public function wait($unwrap = true) {
		if(!isset($this->waitTask)) {
			throw new PromiseWaitTaskNotSetException();
		}

		while($this->getState() === HttpPromiseInterface::PENDING) {
			call_user_func($this->waitTask);
			usleep((int)($this->waitTaskDelay * 1_000_000));
		}

		$this->complete();

		if($unwrap) {
			$resolvedValue = $this->fulfilledValue;
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
			$this->rejectedReason = new PromiseResolvedWithAnotherPromiseException();
			return;
		}

		$this->state = self::FULFILLED;
		$this->fulfilledValue = $value;
	}

	private function reject(Throwable $reason):void {
		$this->state = self::REJECTED;
		$this->rejectedReason = $reason;
	}
}
