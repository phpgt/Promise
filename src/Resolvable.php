<?php
namespace Gt\Promise;

trait Resolvable {
	private function resolve($promiseOrValue = null):PromiseInterface {
		if ($promiseOrValue instanceof PromiseInterface) {
			return $promiseOrValue;
		}

		return new FulfilledPromise($promiseOrValue);
	}
}