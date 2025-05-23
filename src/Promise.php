<?php
namespace Gt\Promise;

use Gt\Promise\Chain\CatchChain;
use Gt\Promise\Chain\Chainable;
use Gt\Promise\Chain\ChainFunctionTypeError;
use Gt\Promise\Chain\FinallyChain;
use Gt\Promise\Chain\ThenChain;
use Throwable;

class Promise implements PromiseInterface {
	use ExecutesPromiseChain;

	private bool $resolvedValueSet = false;
	private bool $stopChain = false;

	private mixed $resolvedValue;
	private mixed $originalResolvedValue;
	private Throwable $rejectedReason;
	/** @var array<Chainable> */
	private array $chain;
	/** @var array<Throwable> */
	private array $handledRejections;
	/** @var callable */
	private $executor;

	public function __construct(callable $executor) {
		$this->chain = [];
		$this->handledRejections = [];
		$this->executor = $executor;
		$this->callExecutor();
	}

	public function getState():PromiseState {
		if(isset($this->rejectedReason)) {
			return PromiseState::REJECTED;
		}
		elseif($this->resolvedValueSet) {
			return PromiseState::RESOLVED;
		}
		return PromiseState::PENDING;
	}

	public function then(callable $onResolved):PromiseInterface {
		array_push($this->chain, new ThenChain($onResolved, null));
		$this->tryComplete();
		return $this;
	}

	private function chainPromise(PromiseInterface $promise):void {
		$this->reset();
		$futureThen = $this->getNextChainItem();
		$promise->then(function(mixed $newResolvedValue)use($futureThen) {
			$futureThen->callOnResolved($newResolvedValue);
			$this->resolve($newResolvedValue);
			$this->tryComplete();
		})->catch(function(Throwable $rejection) {
			$this->reject($rejection);
			$this->tryComplete();
		});
	}

	public function catch(callable $onRejected):PromiseInterface {
		array_push($this->chain, new CatchChain(null, $onRejected));
		$this->tryComplete();
		return $this;
	}

	public function finally(callable $onResolvedOrRejected):PromiseInterface {
		array_push($this->chain, new FinallyChain($onResolvedOrRejected, $onResolvedOrRejected));
		return $this;
	}

	private function callExecutor():void {
		call_user_func(
			$this->executor,
			function(mixed $value = null) {
				try {
					$this->resolve($value);
				}
				catch(PromiseException $exception) {
					$this->reject($exception);
				}
			},
			function(Throwable $reason) {
				$this->reject($reason);
			},
			function() {
				$this->tryComplete();
			}
		);
	}

	private function resolve(mixed $value):void {
		if($this->getState() === PromiseState::RESOLVED) {
			return;
		}
		$this->reset();
		if($value instanceof PromiseInterface) {
			$this->reject(new PromiseResolvedWithAnotherPromiseException());
			$this->tryComplete();
			return;
		}
		$this->resolvedValue = $this->originalResolvedValue = $value;
		$this->resolvedValueSet = true;
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
		if($this->stopChain) {
			$this->stopChain = false;
			return;
		}
		if(empty($this->chain)) {
			$this->throwUnhandledRejection();
			return;
		}
		if($this->getState() !== PromiseState::PENDING) {
			$this->complete();
		}
	}

	private function getNextChainItem():?Chainable {
		return array_shift($this->chain);
	}

	protected function handleThen(ThenChain $then):bool {
		if($this->getState() !== PromiseState::RESOLVED) {
			return false;
		}

		try {
			$result = $then->callOnResolved($this->resolvedValue);
			return $this->handleResolvedResult($result);
		}
		catch(Throwable $rejection) {
			$this->reject($rejection);
		}

		return false;
	}

	protected function handleFinally(FinallyChain $finally):bool {
		if($this->getState() === PromiseState::RESOLVED) {
			$result = $finally->callOnResolved($this->resolvedValue);
			return $this->handleResolvedResult($result);
		}
		elseif($this->getState() === PromiseState::REJECTED) {
			$result = $finally->callOnRejected($this->rejectedReason);
			return $this->handleResolvedResult($result);
		}

		return false;
	}

	private function handleResolvedResult(mixed $result):bool {
		if($result instanceof PromiseInterface) {
			$this->chainPromise($result);
		}
		elseif(is_null($result)) {
			$this->stopChain = true;
			return true;
		}
		else {
			$this->resolvedValue = $result;
			$this->resolvedValueSet = true;
			$this->tryComplete();
		}

		return false;
	}

	protected function handleCatch(CatchChain $catch):?Throwable {
		if($this->getState() !== PromiseState::REJECTED) {
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

	protected function throwUnhandledRejection():void {
		if($this->getState() === PromiseState::REJECTED) {
			if(!in_array($this->rejectedReason, $this->handledRejections)) {
				throw $this->rejectedReason;
			}
		}
	}

	protected function emptyChain():void {
		if(isset($this->originalResolvedValue)) {
			$this->resolvedValue = $this->originalResolvedValue;
		}

		$this->chain = [];
	}
}
