<?php
namespace Gt\Promise\Test\Helper;

use Exception;
use Gt\Promise\Deferred;
use Gt\Promise\PromiseInterface;
use Gt\Promise\PromiseState;
use RuntimeException;
use Throwable;

class CustomPromise {
	private PromiseState $state = PromiseState::PENDING;
	private mixed $resolvedValue;
	private \Throwable $rejectedReason;
	/** @var callable */
	private $onFulfilled;
	/** @var callable */
	private $onRejected;

	public function then(
		?callable $onResolved = null,
		?callable $onRejected = null,
	):?PromiseInterface {
		$newDeferred = new Deferred();
		$newPromise = $newDeferred->getPromise();

		$onResolved = $onResolved
			?? fn($resolvedValue) => $resolvedValue;
		$onRejected = $onRejected
			?? fn(Throwable $exception) => $exception;

		$this->onFulfilled = function(mixed $resolvedValue)
		use($onResolved, $newDeferred) {
			try {
				$return = $onResolved($resolvedValue);

				if($return instanceof PromiseInterface) {
					$return->then(function($innerResolvedValue) use($newDeferred) {
						$newDeferred->resolve($innerResolvedValue);
					});
				}
				else {
					$newDeferred->resolve($return ?? $resolvedValue);
				}
			}
			catch(Exception $exception) {
				$newDeferred->reject($exception);
			}
		};
		$this->onRejected = function(Throwable $rejectedReason)
		use($onRejected, $newDeferred) {
			$return = $onRejected($rejectedReason);
			$newDeferred->reject($return ?? $rejectedReason);
		};

		if($this->state === PromiseState::RESOLVED) {
			$this->doResolve($this->resolvedValue);
		}
		elseif($this->state === PromiseState::REJECTED) {
			$this->doReject($this->rejectedReason);
			call_user_func($this->onRejected, $this->rejectedReason);
		}

		return $newPromise;
	}

	public function getState():PromiseState {
		return $this->state;
	}

	public function wait($unwrap = true):void {
		// TODO: Implement wait() method.
	}

	public function resolve(mixed $value):void {
		if($this->state !== PromiseState::PENDING) {
			throw new RuntimeException("Promise is already resolved");
		}
		$this->state = PromiseState::RESOLVED;
		$this->resolvedValue = $value;
		$this->doResolve($value);
	}

	public function reject(Throwable $reason):void {
		if($this->state !== PromiseState::PENDING) {
			throw new RuntimeException("Promise is already resolved");
		}
		$this->state = PromiseState::REJECTED;
		$this->rejectedReason = $reason;
		$this->doReject($reason);
	}

	private function doResolve(mixed $value):void {
		if(isset($this->onFulfilled)) {
			call_user_func($this->onFulfilled, $value);
		}
	}

	private function doReject(Throwable $reason):void {
		if(isset($this->onRejected)) {
			call_user_func($this->onRejected, $reason);
		}
	}
}
