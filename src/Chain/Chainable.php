<?php
namespace Gt\Promise\Chain;

use ReflectionFunction;
use Throwable;
use TypeError;

abstract class Chainable {
	/** @var callable|null */
	private $onResolved;
	/** @var callable|null */
	private $onRejected;

	public function __construct(
		?callable $onResolved,
		?callable $onRejected
	) {
		$this->onResolved = $onResolved;
		$this->onRejected = $onRejected;
	}

	public function callOnResolved(mixed $value):mixed {
		if(is_null($this->onResolved)) {
			return null;
		}

		return call_user_func($this->onResolved, $value);
	}

	/**
	 * @return mixed|Throwable|null New resolved value
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function callOnRejected(Throwable $reason) {
		if(is_null($this->onRejected)) {
			return $reason;
		}

		return call_user_func($this->onRejected, $reason);
//		try {
//		}
//		catch(TypeError $error) {
//			$reflection = new ReflectionFunction($this->onRejected);
//			$param = $reflection->getParameters()[0] ?? null;
//			if($param) {
//				$paramType = (string)$param->getType();
//
//				if(!str_contains($error->getMessage(), "must be of type $paramType")) {
//					throw $error;
//				}
//			}
//
//			return $reason;
//		}
	}
}
