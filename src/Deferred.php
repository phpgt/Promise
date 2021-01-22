<?php
namespace Gt\Promise;

use Throwable;

class Deferred implements DeferredInterface {
	private Promise $promise;
	/** @var callable */
	private $resolveCallback;
	/** @var callable */
	private $rejectCallback;
	/** @var callable */
	private $completeCallback;
	/** @var callable[] */
	private array $processList;
	/** @var callable[] */
	private array $deferredCompleteCallback;
	private bool $activated;
	/** @var Deferred[] */
	private array $dependantDeferred;

	public function __construct(callable $process = null) {
		$this->promise = new Promise(function($resolve, $reject, $complete):void {
			$this->resolveCallback = $resolve;
			$this->rejectCallback = $reject;
			$this->completeCallback = $complete;
		});

		$this->processList = [];
		$this->deferredCompleteCallback = [];
		$this->activated = true;
		$this->dependantDeferred = [];

		if(!is_null($process)) {
			$this->addProcess($process);
		}
	}

	public function getPromise():Promise {
		return $this->promise;
	}

	public function resolve($value = null):void {
		call_user_func($this->resolveCallback, $value);
		$this->complete();
	}

	public function reject(Throwable $reason):void {
		call_user_func($this->rejectCallback, $reason);
		$this->complete();
	}

	public function addProcess(callable $process):void {
		array_push($this->processList, $process);
	}

	/** @return callable[] */
	public function getProcessList():array {
		return $this->processList;
	}

	public function isActive():bool {
		return $this->activated;
	}

	public function hasActiveDependents():bool {
		foreach($this->dependantDeferred as $deferred) {
			if($deferred->isActive()) {
				return true;
			}
		}

		return false;
	}

	public function addCompleteCallback(callable $callback):void {
		array_push($this->deferredCompleteCallback, $callback);
	}

	private function complete():void {
		if(!$this->activated) {
			return;
		}

		$completionAttempts = 0;
		do {
			if($completionAttempts > 0) {
				$this->process();
			}

			call_user_func($this->completeCallback);
			$completionAttempts++;
		}
		while($this->promise->getState() === "pending"
		|| $this->hasActiveDependents());

		$this->activated = false;
		foreach($this->deferredCompleteCallback as $callback) {
			call_user_func($callback);
		}
	}

	/**
	 * Calls the processes that are assigned to this Deferred, and registers
	 * any returned Deferred objects (or array of Deferred objects) as
	 * processes that will mark the outer Deferred as "complete".
	 */
	private function process():void {
		foreach($this->getProcessList() as $process) {
			$result = call_user_func($process);
			if(!is_array($result)) {
				$result = [$result];
			}
			while($obj = array_shift($result)) {
				if(!$obj instanceof DeferredInterface) {
					break;
				}

				if(!in_array($obj, $this->dependantDeferred)) {
					array_push(
						$this->dependantDeferred,
						$obj
					);
				}
			}
		}

		$this->processDependants();
	}

	private function processDependants():void {
		foreach($this->dependantDeferred as $deferred) {
			$deferred->process();
		}
	}
}