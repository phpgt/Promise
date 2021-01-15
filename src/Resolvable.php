<?php
namespace Gt\Promise;

use Http\Promise\Promise as HttpPromiseInterface;

trait Resolvable {
	/** @param Promise|mixed $promiseOrValue */
	private function resolve($promiseOrValue = null):PromiseInterface {
		if ($promiseOrValue instanceof PromiseInterface) {
			return $promiseOrValue;
		}

		$this->state = HttpPromiseInterface::FULFILLED;

		return new FulfilledPromise($promiseOrValue);
	}
}