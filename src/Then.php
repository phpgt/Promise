<?php
namespace Gt\Promise;

use Throwable;

class Then {
	/** @var callable|null */
	private $onFulfilled;
	/** @var callable|null */
	private $onRejected;

	public function __construct(
		?callable $onFulfilled,
		?callable $onRejected
	) {
		$this->onFulfilled = $onFulfilled;
		$this->onRejected = $onRejected;
	}

	/**
	 * @param mixed|null $value
	 * @return mixed|null New fulfilled value
	 */
	public function callOnFulfilled($value) {
		if(is_null($this->onFulfilled)) {
			return null;
		}

		return call_user_func($this->onFulfilled, $value);
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
}