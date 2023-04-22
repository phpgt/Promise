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

	/**
	 * @param mixed|null $value
	 * @return mixed|null New resolved value
	 */
	public function callOnResolved($value) {
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

		try {
			return call_user_func($this->onRejected, $reason);
		}
		catch(TypeError $error) {
			$reflection = new ReflectionFunction($this->onRejected);
			$param = $reflection->getParameters()[0] ?? null;
			if($param) {
				$paramType = (string)$param->getType();

// TypeError messages behave slightly differently between PHP 7 and 8.
// This strange if block will be dropped when PHP 7.4 support is dropped.
				if(PHP_VERSION[0] >= 8) {
					if(!strstr(
						$error->getMessage(),
						"must be of type $paramType"
					)) {
						throw $error;
					}
				}
				else {
					$paramType = str_replace("\\", "\\\\", $paramType);
					if(!preg_match(
						"/must be (of the type|an instance of) $paramType/",
						$error->getMessage()
					)) {
						throw $error;
					}
				}
			}

			return $reason;
		}
	}
}
