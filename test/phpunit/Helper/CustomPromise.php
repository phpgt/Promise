<?php
namespace Gt\Promise\Test\Helper;

use Exception;
use Gt\Promise\Deferred;
use Gt\Promise\PromiseInterface;
use Http\Promise\Promise as HttpPromiseInterface;
use RuntimeException;
use Throwable;

class CustomPromise implements HttpPromiseInterface {
	private string $state = self::PENDING;
	private mixed $resolvedValue;
	private \Throwable $rejectedReason;
	/** @var callable */
	private $onFulfilled;
	/** @var callable */
	private $onRejected;

	public function then(
		callable $onResolved = null,
		callable $onRejected = null,
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

				if($return instanceof HttpPromiseInterface) {
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

		if($this->state === self::FULFILLED) {
			$this->doResolve($this->resolvedValue);
		}
		elseif($this->state === self::REJECTED) {
			$this->doReject($this->rejectedReason);
			call_user_func($this->onRejected, $this->rejectedReason);
		}

		return $newPromise;
	}

	public function getState():string {
		return $this->state;
	}

	public function wait($unwrap = true):void {
		// TODO: Implement wait() method.
	}

	public function resolve(mixed $value):void {
		if($this->state !== self::PENDING) {
			throw new RuntimeException("Promise is already resolved");
		}
		$this->state = self::FULFILLED;
		$this->resolvedValue = $value;
		$this->doResolve($value);
	}

	public function reject(Throwable $reason):void {
		if($this->state !== self::PENDING) {
			throw new RuntimeException("Promise is already resolved");
		}
		$this->state = self::REJECTED;
		$this->rejectedReason = $reason;
		$this->doReject($reason);
	}

	private function doResolve(mixed $value) {
		if(isset($this->onFulfilled)) {
			call_user_func($this->onFulfilled, $value);
		}
	}

	private function doReject(Throwable $reason) {
		if(isset($this->onRejected)) {
			call_user_func($this->onRejected, $reason);
		}
	}
}
