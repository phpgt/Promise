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

	public function __construct(callable $process = null) {
		$this->promise = new Promise(function($resolve, $reject, $complete):void {
			$this->resolveCallback = $resolve;
			$this->rejectCallback = $reject;
			$this->completeCallback = $complete;
		});

		$this->processList = [];
		$this->deferredCompleteCallback = [];
		$this->activated = true;

		if(!is_null($process)) {
			$this->addProcess($process);
		}
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

	public function onComplete(callable $callback):void {
		array_push($this->deferredCompleteCallback, $callback);
	}

	private function complete():void {
		if(!$this->activated) {
			return;
		}

		call_user_func($this->completeCallback);

		$this->activated = false;
		foreach($this->deferredCompleteCallback as $callback) {
			call_user_func($callback);
		}
	}
}
