<?php
namespace Gt\Promise;

use Gt\Promise\Chain\CatchChain;
use Gt\Promise\Chain\Chainable;
use Gt\Promise\Chain\FinallyChain;
use Gt\Promise\Chain\ThenChain;
use Throwable;
use TypeError;

class Promise implements PromiseInterface {
	private mixed $resolvedValue;
	private ?Throwable $rejectedReason;

	/** @var Chainable[] */
	private array $chain;
	/** @var Chainable[] */
	private array $pendingChain;
	/** @var CatchChain[] */
	private array $uncalledCatchChain;
	/** @var callable */
	private $executor;
	/** @var ?callable */
	private $waitTask;
	private float $waitTaskDelay;

	public function __construct(callable $executor) {
		$this->chain = [];
		$this->pendingChain = [];
		$this->uncalledCatchChain = [];

		$this->executor = $executor;
		$this->callExecutor();
	}

	public function getState():PromiseState {
		if(isset($this->rejectedReason)) {
			return PromiseState::REJECTED;
		}
		elseif(isset($this->resolvedValue)) {
			return PromiseState::RESOLVED;
		}

		return PromiseState::PENDING;
	}

	public function then(callable $onResolved):PromiseInterface {
		array_push($this->chain, new ThenChain(
			$onResolved,
			null,
		));
		$this->tryComplete();
		return $this;
	}

	private function chainPromise(PromiseInterface $promise):void {
		$this->reset();
// TODO: Do we even need this pending chain, or can we just pass $nextThen directly into the $result->then using "use"?
		$nextThen = $this->getNextChainItem();
		array_push($this->pendingChain, $nextThen);

		$promise->then(function(mixed $newResolvedValue) {
			$then = $this->getNextChainItem(true);
			$then->callOnResolved($newResolvedValue);

			$this->resolve($newResolvedValue);
			$this->tryComplete();
		})->catch(function(Throwable $rejection) {
			$this->reject($rejection);
			$this->tryComplete();
		});
	}

	public function catch(callable $onRejected):PromiseInterface {
		array_push($this->chain, new CatchChain(
			null,
			$onRejected
		));
		$this->tryComplete();
		return $this;
	}

	public function finally(
		callable $onResolvedOrRejected
	):PromiseInterface {
		array_push($this->chain, new FinallyChain(
			$onResolvedOrRejected,
			$onResolvedOrRejected
		));

		return $this;
	}

	private function callExecutor():void {
		call_user_func(
			$this->executor,
			function(mixed $value = null) {
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

	private function resolve(mixed $value):void {
		$this->reset();
		if($value instanceof PromiseInterface) {
			$this->reject(new PromiseResolvedWithAnotherPromiseException());
			$this->tryComplete();
			return;
		}

		$this->resolvedValue = $value;
	}

	private function reject(Throwable $reason):void {
		$this->reset();
		$this->rejectedReason = $reason;
	}

	private function reset():void {
		if(isset($this->resolvedValue)) {
			unset($this->resolvedValue);
		}
		if(isset($this->rejectedReason)) {
			unset($this->rejectedReason);
		}
	}

	private function tryComplete():void {
		if($this->getState() !== PromiseState::PENDING) {
			$this->complete();
		}
	}

	private function complete(
	):void {
		$this->sortChain();
		$this->handleChain();
	}

	private function sortChain():void {
		usort($this->chain, fn($a, $b)
		=> $a instanceof FinallyChain ? 1 : 0);
	}

	private function handleChain():void {
		$handledRejections = [];

		$emptyChain = empty($this->chain);
		while($this->getState() !== PromiseState::PENDING) {
			$chainItem = $this->getNextChainItem();
			if(!$chainItem) {
				break;
			}
			// TODO: Explore different syntax: $chainItem = $this->getNextChainItem() or break;

			if($chainItem instanceof ThenChain) {
				$this->handleThen($chainItem);
			}
			elseif($chainItem instanceof CatchChain) {
				if($handled = $this->handleCatch($chainItem)) {
					array_push($handledRejections, $handled);
				}
			}
			elseif($chainItem instanceof FinallyChain) {
				$this->handleFinally($chainItem);
			}
		}

		if(isset($this->rejectedReason)) {
			if(!$emptyChain && !in_array($this->rejectedReason, $handledRejections)) {
				throw $this->rejectedReason;
			}
		}
	}

	private function getNextChainItem(bool $pending = false):?Chainable {
		if($pending) {
			return array_shift($this->pendingChain);
		}

		return array_shift($this->chain);
	}

	private function handleThen(ThenChain $then):void {
		if($this->getState() !== PromiseState::RESOLVED) {
			return;
		}

		try {
			$result = $then->callOnResolved($this->resolvedValue) ?? $this->resolvedValue;

			if($result instanceof PromiseInterface) {
				$this->chainPromise($result);
			}
			else {
				$this->resolve($result);
			}
		}
		catch(Throwable $rejection) {
			$this->reject($rejection);
		}
	}

	private function handleCatch(CatchChain $catch):?Throwable {
		if($this->getState() !== PromiseState::REJECTED) {
// TODO: This list of uncalled catch chains can be processed later if there's an exception that matches.
			array_push($this->uncalledCatchChain, $catch);
			return null;
		}

		try {
			$result = $catch->callOnRejected($this->rejectedReason);

			if($result instanceof PromiseInterface) {
				$this->chainPromise($result);
			}
			elseif(!is_null($result)) {
				$this->resolve($result);
			}
			else {
				return $this->rejectedReason;
			}

		}
		catch(Throwable $rejection) {
			$this->reject($rejection);
		}

		return null;
	}

	private function handleFinally(FinallyChain $finally):void {
		$result = null;

		if($this->getState() === PromiseState::RESOLVED) {
			$result = $finally->callOnResolved($this->resolvedValue);
		}
		elseif($this->getState() === PromiseState::REJECTED) {
			$result = $finally->callOnRejected($this->rejectedReason);
		}

		if($result instanceof PromiseInterface) {
			$this->chainPromise($result);
		}
		else {
			$this->resolve($result);
		}
	}

	private function OLD_handleChain():void {
		while($chainItem = array_shift($this->chain)) {
			try {
				if($chainItem instanceof ThenChain && $this->getState() === PromiseState::RESOLVED) {
					if(isset($this->resolvedValue)) {
						$newValue = $chainItem->callOnResolved($this->resolvedValue);
						if($newValue instanceof PromiseInterface) {
							unset($this->resolvedValue);
							array_push($this->pendingChain, $this->chain[0] ?? null);
							$newValue->then(function(mixed $resolvedValue) {
								$this->resolvedValue = $resolvedValue;
								if($chainItem = array_pop($this->pendingChain)) {
									$chainItem->callOnResolved($this->resolvedValue);
									unset($this->resolvedValue);
								}
								$this->complete();
							})->catch(function(Throwable $reason) {
								$this->reject($reason);
							});
						}
						elseif(!is_null($newValue)) {
							$this->resolve($newValue);
						}
					}
				}
				if($chainItem instanceof CatchChain && $this->getState() === PromiseState::REJECTED) {
					$newValue = $chainItem->callOnRejected($this->rejectedReason);

					if(!is_null($newValue)) {
						if($newValue instanceof Throwable) {
							$this->reject($newValue);
						}
						else {
							$this->resolve($newValue);
						}
					}
				}
				if($chainItem instanceof FinallyChain) {
					if($this->getState() === PromiseState::RESOLVED) {
						$chainItem->callOnResolved($this->resolvedValue);
					}
					elseif($this->getState() === PromiseState::REJECTED) {
						$chainItem->callOnRejected($this->rejectedReason);
					}
				}
			}
			catch(Throwable $rejection) {
// When the chain is being handled, each item in the chain will be called.
// If there's a rejection, we will pass it to the next item in the chain, unless
// the chain is empty - at which point we must throw the rejection to the main
// thread. This allows for different catch functions to have different type
// hints, so each type of Throwable can be individually handled.
				if(empty($this->chain)) {
					throw $rejection;
				}
				else {
					$this->reject($rejection);
				}
			}
		}
	}

	public function setWaitTask(
		callable $task,
		float $delaySeconds = 0.01
	):void {
		$this->waitTask = $task;
		$this->waitTaskDelay = $delaySeconds;
	}

	public function wait(bool $unwrap = true):mixed {
		if(!isset($this->waitTask)) {
			throw new PromiseWaitTaskNotSetException();
		}

		while($this->getState() === PromiseState::PENDING) {
			call_user_func($this->waitTask);
			usleep((int)($this->waitTaskDelay * 1_000_000));
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
}
