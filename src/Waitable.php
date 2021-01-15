<?php
namespace Gt\Promise;

use Http\Promise\Promise as HttpPromiseInterface;

/**
 * This trait allows HTTPlug compatibility by registering a callback (the "wait
 * task") so the Promise itself can wait for its own resolution.
 */
trait Waitable {
	/** @var callable */
	private $waitTask;

	public function setWaitTask(callable $task):void {
		$this->waitTask = $task;
	}

	/** @param bool $unwrap */
	public function wait($unwrap = true):mixed {
		if(!isset($this->waitTask)) {
			throw new PromiseWaitTaskNotSetException();
		}

		/** @var Promise $promise */
		$promise = $this;
		if($unwrap && $this instanceof Promise) {
			$promise = $this->unwrap($promise);
		}

		while($promise->getState() === HttpPromiseInterface::PENDING) {
			call_user_func($this->waitTask);
		}

		if($unwrap) {
			$resolvedValue = null;
			$promise->then(function(mixed $value) use(&$resolvedValue):void {
				$resolvedValue = $value;
			});
			return $resolvedValue;
		}

		return null;
	}
}