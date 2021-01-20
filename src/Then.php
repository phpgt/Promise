<?php
namespace Gt\Promise;

use Throwable;

class Then {
	/** @var callable|null */
	private $onFulfilled;
	/** @var callable|null */
	private $onRejected;
	/** @var Throwable[] */
	private array $rejectionList;

	public function __construct(
		?callable $onFulfilled,
		?callable $onRejected
	) {
		$this->onFulfilled = $onFulfilled;
		$this->onRejected = $onRejected;
		$this->rejectionList = [];
	}

	/**
	 * @param mixed|null $value
	 * @return mixed|null New fulfilled value
	 */
	public function callOnFulfilled($value) {
		if(is_null($this->onFulfilled)) {
			return null;
		}

		try {
			return call_user_func($this->onFulfilled, $value);
		}
		catch(Throwable $reason) {
			array_push($this->rejectionList, $reason);
		}
	}

	/**
	 * @return mixed|null New fulfilled value
	 */
	public function callOnRejected(Throwable $reason) {
		if(is_null($this->onRejected)) {
			return $reason;
		}

		return call_user_func($this->onRejected, $reason);
	}

	public function addRejection(Throwable $reason):void {
		array_push($this->rejectionList, $reason);
	}

	public function getRejection():?Throwable {
		return array_pop($this->rejectionList);
	}
}