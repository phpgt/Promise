<?php
namespace Gt\Promise;

use Throwable;

class PromiseResolvedWithAnotherPromiseException extends PromiseException {
	const DEFAULT_MESSAGE = "A Promise must be resolved with a concrete value, not another Promise.";

	public function __construct(
		string $message = self::DEFAULT_MESSAGE,
		int $code = 0,
		Throwable $previous = null
	) {
		parent::__construct($message, $code, $previous);
	}
}