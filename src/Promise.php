<?php
namespace Gt\Promise;

use Throwable;
use Http\Promise\Promise as HttpPromiseInterface;

class Promise implements PromiseInterface, HttpPromiseInterface {
	private string $state;
	/** @var mixed */
	private $resolvedValue;
	/** @var Then[] */
	private array $thenQueue;
	/** @var callable */
	private $executor;
	private ?Throwable $rejection;

	public function __construct(callable $executor) {
		$this->state = HttpPromiseInterface::PENDING;
		$this->thenQueue = [];
		$this->rejection = null;
		
		$this->executor = $executor;
		$this->call();
	}

	public function then(
		callable $onFulfilled = null,
		callable $onRejected = null
	):PromiseInterface {
		array_push($this->thenQueue, new Then(
			$onFulfilled,
			$onRejected
		));

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
		// TODO: Implement finally() method.
	}

	public function complete(
		callable $onFulfilled = null,
		callable $onRejected = null
	):void {
		$this->then($onFulfilled, $onRejected);
		$this->handleThens();
	}

	public function handleThens():void {
		$rejectedForwardQueue = [];

		foreach($this->thenQueue as $i => $then) {
			try {
				if($reason = $then->getRejection()
				?? $this->rejection
				?? array_pop($rejectedForwardQueue)
				?? null) {
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
					if(!is_null($value)) {
						$this->resolvedValue = $value;
					}
				}
			}
			catch(Throwable $reason) {
				array_push($rejectedForwardQueue, $reason);
			}

			while($reason = $then->getRejection()) {
				array_push($rejectedForwardQueue, $reason);
			}
		}
	}

	public function getState():string {
		return $this->state;
	}

	/** @param bool $unwrap */
	public function wait($unwrap = true) {
		// TODO: Implement wait() method.
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
			}
		);
	}

	/** @param mixed $value */
	private function resolve($value):void {
		if($value instanceof PromiseInterface) {
			$reason = new PromiseResolvedWithAnotherPromiseException();

			$this->rejection = $reason;
//			if(empty($this->thenQueue)) {
//			}
//			else {
//				$this->thenQueue[0]->addRejection($reason);
//			}

			return;
		}

		$this->resolvedValue = $value;
	}

	private function reject(Throwable $reason):void {
		$this->rejection = $reason;
	}
}