<?php
namespace Gt\Promise\Test;

use Gt\Promise\PromiseInterface;

class TestPromiseContainer {
	private PromiseInterface $promise;
	/** @var ?callable */
	private $resolve;
	/** @var ?callable */
	private $reject;
	/** @var ?callable */
	private $settle;

	public function __construct(
		PromiseInterface $promise,
		callable $resolve = null,
		callable $reject = null,
		callable $settle = null
	) {
		$this->promise = $promise;
		$this->resolve = $resolve;
		$this->reject = $reject;
		$this->settle = $settle;
	}

	public function getPromise():PromiseInterface {
		return $this->promise;
	}

	public function resolve(...$args):?PromiseInterface {
		return call_user_func($this->resolve, ...$args);
	}

	public function reject(...$args):?PromiseInterface {
		return call_user_func($this->reject, ...$args);
	}

	public function settle(...$args):?PromiseInterface {
		return call_user_func($this->settle, ...$args);
	}
}