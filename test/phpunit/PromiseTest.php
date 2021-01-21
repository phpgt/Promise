<?php
namespace Gt\Promise\Test;

use DateTime;
use Exception;
use Gt\Promise\Promise;
use Gt\Promise\PromiseException;
use Gt\Promise\PromiseResolvedWithAnotherPromiseException;
use Gt\Promise\PromiseWaitTaskNotSetException;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RangeException;
use stdClass;
use Throwable;
use Http\Promise\Promise as HttpPromiseInterface;
use TypeError;

class PromiseTest extends TestCase {
	public function testOnFulfilledResolvesCorrectValue() {
		$value = "Example resolution value";
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(1, $value)
		);

		$promiseContainer->resolve($value);
	}

	public function testOnFulfilledShouldForwardValue() {
		$value = "Example resolution value";
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$sut->then(
			null,
			self::mockCallable(0),
		)->then(
			self::mockCallable(1, $value),
			self::mockCallable(0),
		);

		$promiseContainer->resolve($value);
	}


	public function testPromiseRejectsIfResolvedWithItself() {
		$actualMessage = null;

		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$fulfilledCallCount = 0;
		$sut->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			function(PromiseException $reason) use(&$actualMessage) {
				$actualMessage = $reason->getMessage();
			}
		);

		$promiseContainer->resolve($sut);
		self::assertEquals(0, $fulfilledCallCount);
		self::assertEquals(
			"A Promise must be resolved with a concrete value, not another Promise.",
			$actualMessage
		);
	}

	public function testOnFulfillShouldRejectWhenResolvedWithPromiseInSameChain() {
		$caughtReasons = [];

		$promiseContainer1 = $this->getTestPromiseContainer();
		$promiseContainer2 = $this->getTestPromiseContainer();
		$sut1 = $promiseContainer1->getPromise();
		$sut2 = $promiseContainer2->getPromise();

		$sut2->then(
			self::mockCallable(0),
			function(PromiseResolvedWithAnotherPromiseException $reason) use(&$caughtReasons) {
				array_push($caughtReasons, $reason);
			}
		);

		$promiseContainer1->resolve($sut2);
		$promiseContainer2->resolve($sut1);
		self::assertCount(1, $caughtReasons);
	}

	public function testRejectWithException() {
		$exception = new Exception("Example");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$fulfilledCallCount = 0;

		$sut->then(
			function() use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			self::mockCallable(1, $exception)
		);

		$promiseContainer->reject($exception);
		self::assertEquals(0, $fulfilledCallCount);
	}

	public function testRejectForwardsException() {
		$exception = new Exception("Forward me!");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$fulfilledCallCount = 0;
		$sut->then(
			function() use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			}
		)->then(
			function() use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			self::mockCallable(1, $exception),
		);

		$promiseContainer->reject($exception);
		self::assertEquals(0, $fulfilledCallCount);
	}

	/**
	 * This behaviour might not look correct at first, but it is
	 * made this way to be compatible with the W3C Promise specification.
	 *
	 * An exception being thrown within the then() onFulfilled callback
	 * will not trigger the optional onRejected callback, but it should
	 * be caught in the catch() function.
	 *
	 * @see https://codepen.io/g105b/pen/vYXPoGG?editors=0011
	 */
	public function testRejectIfFulfillerThrowsException() {
		$exception = new Exception("Thrown from within onFulfilled!");
		$promiseContainer = self::getTestPromiseContainer();

		$sut = $promiseContainer->getPromise();
		$sut->then(
			function() use($exception) {
				throw $exception;
			},
			self::mockCallable(0),
		)->catch(
			self::mockCallable(1, $exception)
		);

		$promiseContainer->resolve("success");
	}

	public function testRejectIfRejecterThrowsException() {
		$exception = new Exception("another-test");
		$caughtExceptions = [];

		$promiseContainer = self::getTestPromiseContainer();

		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(0),
			function(Throwable $reason) use($exception) {
				throw $exception;
			},
		)->then(
			self::mockCallable(0),
			function(Throwable $reason) use(&$caughtExceptions) {
				array_push($caughtExceptions, $reason);
			}
		);

		$promiseContainer->reject($exception);
		self::assertCount(1, $caughtExceptions);
	}

	public function testLatestResolvedValueUsedOnFulfillment() {
		$promiseContainer = $this->getTestPromiseContainer();
		$onFulfilled = self::mockCallable(1, "example1");
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);

		$promiseContainer->resolve("example1");
		$promiseContainer->resolve("example2");
	}

	public function testLatestRejectedReasonUsedOnRejection() {
		$promiseContainer = $this->getTestPromiseContainer();
		$exception1 = new Exception("First exception");
		$exception2 = new Exception("Second exception");

		$onFulfilled = self::mockCallable(0);
		$onRejected = self::mockCallable(1, $exception1);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);

		$promiseContainer->reject($exception1);
		$promiseContainer->reject($exception2);
	}

	public function testPreResolvedPromiseInvokesOnFulfill() {
		$promiseContainer = $this->getTestPromiseContainer();
		$onFulfilled = self::mockCallable(1);
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);
		$promiseContainer->resolve("example");
	}

	public function testThenResultForwardedWhenOnFulfilledIsNull() {
		$message = "example resolution message";

		$promiseContainer = $this->getTestPromiseContainer();

		$onFulfilled = self::mockCallable(1, $message);
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			null,
			$onRejected
		)->then(
			$onFulfilled,
			$onRejected
		);

		$promiseContainer->resolve($message);
	}

	public function testThenCallbackResultForwarded() {
		$message = "Hello";
		$messageConcat = "PHP.Gt";
		$expectedResolvedMessage = "$message, $messageConcat!!!";

		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$onFulfilled = self::mockCallable(
			1,
			$expectedResolvedMessage
		);
		$onRejected = self::mockCallable(0);

		$sut->then(function(string $message) use($messageConcat) {
			return "$message, $messageConcat";
		})->then(function(string $message) {
			return "$message!!!";
		})->then(
			$onFulfilled,
			$onRejected
		);

		$promiseContainer->resolve($message);
	}

	/**
	 * A rejected promise should forward its rejection to the end of the
	 * promise chain.
	 * @see https://codepen.io/g105b/pen/BaLENgX?editors=0011
	 */
	public function testThenRejectionCallbackResultForwarded() {
		$promiseContainer = $this->getTestPromiseContainer();
		$expectedException = new Exception("Reject the whole chain");

		$fulfilledCallCount = 0;

		$sut = $promiseContainer->getPromise();
		$sut->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			null,
		)->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
		null,
		)->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			self::mockCallable(1, $expectedException),
		);

		$promiseContainer->reject($expectedException);
		self::assertEquals(0, $fulfilledCallCount);
	}

	/**
	 * If a rejection returns a value, the next chained promise should
	 * resolve with the value.
	 * @see https://codepen.io/g105b/pen/LYRvpNJ?editors=0011
	 */
	public function testThenProvidedResolvedValueAfterRejectionReturnsValue() {
		$message = "If a rejection returns a value, the next chained "
			. "promise should resolve with the value";

		$exception = new Exception("Test Exception!");

		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(0),
			fn() => $message,
		)->then(
			self::mockCallable(1, $message),
			self::mockCallable(0)
		);

		$promiseContainer->reject($exception);
	}

	public function testCatchCalledForRejectedPromise() {
		$exception = new Exception("Example");
		$promiseContainer = $this->getTestPromiseContainer();

		$sut = $promiseContainer->getPromise();
		$sut->catch(
			self::mockCallable(1, $exception),
		);

		$promiseContainer->reject($exception);
	}

	public function testCatchRejectionReasonIdenticalToRejectionException() {
		$exception = new Exception("Example");
		$promiseContainer = $this->getTestPromiseContainer();

		$onRejected = self::mockCallable(1, $exception);

		$sut = $promiseContainer->getPromise();
		$sut->catch(function($reason) use($onRejected) {
			call_user_func($onRejected, $reason);
		});
		$promiseContainer->reject($exception);
	}

	public function testCatchRejectionHandlerIsCalledByTypeHintedOnRejectedCallback() {
		$exception = new PromiseException("Example");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$onRejected = self::mockCallable(1, $exception);

		$sut->catch(function(PromiseException $reason) use($onRejected) {
			call_user_func($onRejected, $reason);
		});

		$promiseContainer->reject($exception);
	}

	public function testCatchRejectionHandlerIsNotCalledByTypeHintedOnRejectedCallback() {
		$exception = new RangeException();
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$onRejected = self::mockCallable(0);
		self::expectException(RangeException::class);

		$sut->catch(function(PromiseException $reason) use($onRejected) {
			call_user_func($onRejected, $reason);
		});

		$promiseContainer->reject($exception);
	}

	public function testCatchRejectionHandlerIsCalledByAnotherMatchingTypeHintedOnRejectedCallback() {
		$exception = new RangeException();
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$onRejected1 = self::mockCallable(0);
		$onRejected2 = self::mockCallable(1);

		$sut->catch(function(PromiseException $reason) use($onRejected1) {
			call_user_func($onRejected1, $reason);
		})->catch(function(RangeException $reason) use($onRejected2) {
			call_user_func($onRejected2, $reason);
		});

		$promiseContainer->reject($exception);
	}

	public function testMatchingTypedCatchRejectionHandlerCanHandleInternalTypeErrors() {
		$exception = new RangeException();
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$onRejected1 = self::mockCallable(0);
		$onRejected2 = self::mockCallable(0);

		// There is a type error in the matching catch callback. This
		// should bubble out of the chain rather than being seen as
		// missing the RangeException type hint.
		self::expectException(TypeError::class);
		self::expectExceptionMessage("DateTime::__construct(): Argument #1 (\$datetime) must be of type string, Closure given");

		$sut->catch(function(PromiseException $reason1) use($onRejected1) {
			call_user_func($onRejected1, $reason1);
		})->catch(function(RangeException $reason2) use($onRejected2) {
			$error = new DateTime(fn() => "this is so wrong");
			call_user_func($onRejected2, $reason2);
		});
		$promiseContainer->reject($exception);
	}

	public function testCatchNotCalledOnFulfilledPromise() {
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->catch(self::mockCallable(0));
		$promiseContainer->resolve("example");
	}

	public function testFinallyDoesNotBlockOnFulfilled() {
		$expectedValue = "Don't break promises!";
		$promiseContainer = $this->getTestPromiseContainer();

		$sut = $promiseContainer->getPromise();
		$sut->finally(fn() => "example123")
		->then(self::mockCallable(1, $expectedValue));

		$promiseContainer->resolve($expectedValue);
	}

	public function testFinallyDoesNotBlockOnRejected() {
		$exception = new Exception();
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->finally(function() {})
		->then(
			null,
			self::mockCallable(1, $exception),
		);

		$promiseContainer->reject($exception);
	}

	public function testFinallyDoesNotBlockOnRejectedWhenReturnsScalar() {
		$exception = new Exception();
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->finally(function() {
			return "Arbitrary scalar value";
		})->then(
			null,
			self::mockCallable(1, $exception),
		);
		$promiseContainer->reject($exception);
	}

	public function testFinallyPassesThrownException() {
		$exception1 = new Exception("First");
		$exception2 = new Exception("Second");
		$promiseContainer = $this->getTestPromiseContainer();

		self::expectException(Exception::class);
		self::expectExceptionMessage("Second");
		$sut = $promiseContainer->getPromise();
		$sut->finally(function() use($exception2) {
			throw $exception2;
		})->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception1),
		);
		$promiseContainer->reject($exception1);
	}

	public function testOnRejectedCalledWhenFinallyThrows() {
		$exception = new PromiseException("Oh dear, oh dear");
		$promiseContainer = $this->getTestPromiseContainer();

		self::expectException(PromiseException::class);
		self::expectExceptionMessage("Oh dear, oh dear");
		$sut = $promiseContainer->getPromise();
		$sut->finally(function() use($exception) {
			throw $exception;
		})->then(
			self::mockCallable(1, "Example resolution"),
			self::mockCallable(0)
		);
		$promiseContainer->resolve("Example resolution");
	}

	public function testGetStatePending() {
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		self::assertEquals(
			HttpPromiseInterface::PENDING,
			$sut->getState()
		);
	}

	public function testGetStateFulfilled() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("Example resolution");
		$sut = $promiseContainer->getPromise();

		self::assertEquals(
			HttpPromiseInterface::FULFILLED,
			$sut->getState()
		);
	}

	public function testGetStateRejected() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject(new Exception("Example rejection"));
		$sut = $promiseContainer->getPromise();

		self::assertEquals(
			HttpPromiseInterface::REJECTED,
			$sut->getState()
		);
	}

	/**
	 * This test is almost identical to the next one. Inside a try-catch
	 * block, it executes a then-catch chain. It asserts that the catch
	 * callback is provided the expected exception, and that the exception
	 * does not bubble out and into the catch block.
	 */
	public function testCatchMethodNotBubblesThrowables() {
		$expectedException = new Exception("Test exception");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$onRejected = self::mockCallable(1, $expectedException);

		$exception = null;
		try {
			$sut->then(function() use($expectedException) {
				throw $expectedException;
			})
			->catch($onRejected);
		}
		catch(Throwable $exception) {}

		$promiseContainer->resolve("test");
		self::assertNull($exception);
	}

	/**
	 * This test tests the opposite of the previous one: if there is no
	 * catch function in the promise chain, an exception should bubble up
	 * and be caught by the try-catch block.
	 */
	public function testNoCatchMethodBubblesThrowables() {
		$expectedException = new Exception("Test exception");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$exception = null;
		try {
			$sut->then(function() use($expectedException) {
				throw $expectedException;
			});
			$promiseContainer->resolve("test");
		}
		catch(Throwable $exception) {}

		self::assertSame($expectedException, $exception);
	}

	public function testWait() {
		$callCount = 0;
		$resolveCallback = null;
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$resolvedValue = "Done!";
		$sut = new Promise($executor);

		$waitTask = function() use(&$callCount, $resolveCallback, $resolvedValue) {
			if($callCount >= 10) {
				call_user_func($resolveCallback, $resolvedValue);
			}
			else {
				$callCount++;
			}
		};

		$sut->setWaitTask($waitTask);
		self::assertEquals($resolvedValue, $sut->wait(true));
		self::assertEquals(10, $callCount);
	}

	public function testWaitNotUnwrapped() {
		$callCount = 0;
		$resolveCallback = null;
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$resolvedValue = "Done!";
		$sut = new Promise($executor);

		$waitTask = function() use(&$callCount, $resolveCallback, $resolvedValue) {
			if($callCount >= 10) {
				call_user_func($resolveCallback, $resolvedValue);
			}
			else {
				$callCount++;
			}
		};

		$sut->setWaitTask($waitTask);
		self::assertNull($sut->wait(false));
		self::assertEquals(10, $callCount);
	}

	public function testWaitUnwrapsFinalValue() {
		$callCount = 0;
		$resolveCallback = null;
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$resolvedValue = "Done!";
		$sut = new Promise($executor);
		$sut->then(function($fulfilled) {
			return "Returned from within onFulfilled!";
		});

		$waitTask = function() use(&$callCount, $resolveCallback, $resolvedValue) {
			if($callCount >= 10) {
				call_user_func($resolveCallback, $resolvedValue);
			}
			else {
				$callCount++;
			}
		};

		$sut->setWaitTask($waitTask);
		self::assertEquals(
			"Returned from within onFulfilled!",
			$sut->wait(true)
		);
		self::assertEquals(10, $callCount);
	}

	public function testWaitWithNoWaitTask() {
		$executor = function(callable $resolve, callable $reject) use(&$resolveCallback):void  {
			$resolveCallback = $resolve;
		};
		$sut = new Promise($executor);;
		self::expectException(PromiseWaitTaskNotSetException::class);
		$sut->wait();
	}

	protected function getTestPromiseContainer():TestPromiseContainer {
		$resolveCallback = null;
		$rejectCallback = null;
		$completeCallback = null;

		$promise = new Promise(function($resolve, $reject, $complete)
		use(&$resolveCallback, &$rejectCallback, &$completeCallback) {
			$resolveCallback = $resolve;
			$rejectCallback = $reject;
			$completeCallback = $complete;
		});

		return new TestPromiseContainer(
			$promise,
			$resolveCallback,
			$rejectCallback,
			$completeCallback
		);
	}

	/** @return MockObject|callable */
	protected function mockCallable(
		int $numCalls = null,
		...$expectedParameters
	):MockObject {
		$mock = self::getMockBuilder(
			stdClass::class
		)->addMethods([
			"__invoke"
		])->getMock();

		if(!is_null($numCalls)) {
			$expectation = $mock->expects(self::exactly($numCalls))
				->method("__invoke");

			if(!empty($expectedParameters)) {
				foreach($expectedParameters as $p) {
					$expectation->with(self::identicalTo($p));
				}
			}
		}

		return $mock;
	}

	/** @return MockObject|callable */
	protected function mockCallableThrowsException(
		Exception $exception,
		int $numCalls,
		...$expectedParameters
	):MockObject {
		$mock = self::mockCallable($numCalls, ...$expectedParameters);
		$mock->method("__invoke")
			->will(self::throwException($exception));
		return $mock;
	}
}