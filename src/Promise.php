<?php
namespace Gt\Promise;

use Gt\Promise\Chain\CatchChain;
use Gt\Promise\Chain\Chainable;
use Gt\Promise\Chain\FinallyChain;
use Gt\Promise\Chain\ThenChain;
use Throwable;
use TypeError;

class Promise implements PromiseInterface {
	private PromiseState $state;
	private mixed $resolvedValue;
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
		$this->state = PromiseState::PENDING;
		$this->chain = [];
		$this->pendingChain = [];
		$this->rejectedReason = null;

		$this->executor = $executor;
		$this->callExecutor();
	}

	public function then(callable $onResolved):PromiseInterface {
		array_push($this->chain, new ThenChain(
			$onResolved,
			null,
		));
		$this->tryComplete();
		return $this;
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
		if($value instanceof PromiseInterface) {
			$this->reject(new PromiseResolvedWithAnotherPromiseException());
			return;
		}

		$this->state = PromiseState::RESOLVED;
		$this->resolvedValue = $value;
	}

	private function reject(Throwable $reason):void {
		$this->state = PromiseState::REJECTED;
		$this->rejectedReason = $reason;
	}

	private function tryComplete():void {
		if(isset($this->resolvedValue) || isset($this->rejectedReason)) {
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
					else {
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

	public function getState():PromiseState {
// TODO: In resolve() and reject() there probably shouldn't be an assignment
// to $this->state. In fact it probably shouldn't be a property at all... it
// can be inferred by whether there is a value set to resolvedValue
// or rejectedReason... maybe - this needs checking.
		return $this->state;
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
