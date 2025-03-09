<?php
namespace Gt\Promise;

use Throwable;

class PromiseResolvedWithAnotherPromiseException extends PromiseException {
	const DEFAULT_MESSAGE = "A Promise must not be resolved with another Promise.";

	public function __construct(
		string $message = "",
		int $code = 0,
		?Throwable $previous = null
	) {
		parent::__construct(
			$message ?: self::DEFAULT_MESSAGE,
			$code,
			$previous
		);
	}
}
