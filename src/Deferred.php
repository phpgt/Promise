<?php
namespace Gt\Promise;

use Throwable;

class Deferred implements DeferredInterface {
	private Promise $promise;
	/** @var callable */
	private $resolveCallback;
	/** @var callable */
	private $rejectCallback;
	/** @var callable[] */
	private array $processList;
	/** @var callable[] */
	private array $completeCallback;
	private bool $activated;

	public function __construct(callable $process = null) {
		$this->promise = new Promise(function($resolve, $reject):void {
			$this->resolveCallback = $resolve;
			$this->rejectCallback = $reject;
		});

		$this->processList = [];
		$this->completeCallback = [];
		$this->activated = true;

		if(!is_null($process)) {
			$this->addProcess($process);
		}
	}

	public function getPromise():Promise {
		return $this->promise;
	}

	public function resolve($value = null):void {
		$this->complete();
		call_user_func($this->resolveCallback, $value);
	}

	public function reject(Throwable $reason):void {
		$this->complete();
		call_user_func($this->rejectCallback, $reason);
	}

	private function complete():void {
		if(!$this->activated) {
			return;
		}

		$this->activated = false;
		foreach($this->completeCallback as $callback) {
			call_user_func($callback);
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

	public function addCompleteCallback(callable $callback):void {
		array_push($this->completeCallback, $callback);
	}
}