<?php
namespace Gt\Promise\Test;

use Exception;
use Gt\Promise\Promise;
use Gt\Promise\PromiseException;
use Gt\Promise\PromiseResolvedWithAnotherPromiseException;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RangeException;
use stdClass;
use Throwable;
use Http\Promise\Promise as HttpPromiseInterface;

class PromiseTest extends TestCase {
	public function testOnFulfilledResolvesCorrectValue() {
		$value = "Example resolution value";
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve($value);
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(1, $value)
		);

		$sut->complete();
	}

	public function testOnFulfilledShouldForwardValue() {
		$value = "Example resolution value";
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve($value);
		$sut = $promiseContainer->getPromise();

		$sut->then(
			null,
			self::mockCallable(0),
		)->then(
			self::mockCallable(1, $value),
			self::mockCallable(0),
		);

		$sut->complete();
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
		$sut->complete();
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
		$sut2->complete();
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
		$sut->complete();

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
		$sut->complete();
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
		$exception = new Exception("another-test");
		$promiseContainer = self::getTestPromiseContainer();
		$promiseContainer->resolve("success");

		$sut = $promiseContainer->getPromise();
		$sut->then(
			function() use($exception) {
				throw $exception;
			},
			self::mockCallable(0),
		)->catch(
			self::mockCallable(1, $exception)
		);
		$sut->complete();
	}

	public function testRejectIfRejecterThrowsException() {
		$exception = new Exception("another-test");
		$caughtExceptions = [];

		$promiseContainer = self::getTestPromiseContainer();
		$promiseContainer->reject($exception);

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
		$sut->complete();

		self::assertCount(1, $caughtExceptions);
	}

	public function testLatestResolvedValueUsedOnFulfillment() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("example1");
		$promiseContainer->resolve("example2");
		$onFulfilled = self::mockCallable(1, "example2");
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);
		$sut->complete();
	}

	public function testLatestRejectedReasonUsedOnRejection() {
		$promiseContainer = $this->getTestPromiseContainer();
		$exception1 = new Exception("First exception");
		$exception2 = new Exception("Second exception");

		$promiseContainer->reject($exception1);
		$promiseContainer->reject($exception2);

		$onFulfilled = self::mockCallable(0);
		$onRejected = self::mockCallable(1, $exception2);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);
		$sut->complete();
	}

	public function testPreResolvedPromiseInvokesOnFulfill() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("example");
		$onFulfilled = self::mockCallable(1);
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);
		$sut->complete();
	}

	public function testThenResultForwardedWhenOnFulfilledIsNull() {
		$message = "example resolution message";

		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve($message);

		$onFulfilled = self::mockCallable(1, $message);
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			null,
			$onRejected
		)->then(
			$onFulfilled,
			$onRejected
		)->complete();
	}

	public function testThenCallbackResultForwarded() {
		$message = "Hello";
		$messageConcat = "PHP.Gt";
		$expectedResolvedMessage = "$message, $messageConcat!!!";

		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve($message);
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
		)->complete();
	}

	/**
	 * A rejected promise should forward its rejection to the end of the
	 * promise chain.
	 * @see https://codepen.io/g105b/pen/BaLENgX?editors=0011
	 */
	public function testThenRejectionCallbackResultForwarded() {
		$promiseContainer = $this->getTestPromiseContainer();
		$expectedException = new Exception("Reject the whole chain");
		$promiseContainer->reject($expectedException);

		$fulfilledCallCount = 0;

		$sut = $promiseContainer->getPromise();
		$sut->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			fn() => null,
		)->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			fn() => null,
		)->then(
			function($value) use(&$fulfilledCallCount) {
				$fulfilledCallCount++;
			},
			self::mockCallable(1, $expectedException),
		)->complete();

		self::assertEquals(0, $fulfilledCallCount);
	}

//	public function testThenProvidedResolvedValueAfterRejectionReturnsValue() {
//		$message = "If a rejection returns a value, the next chained "
//			. "promise should resolve with the value";
//
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject(new Exception());
//		$sut = $promiseContainer->getPromise();
//		$sut->then(
//			self::mockCallable(0),
//			fn() => $message,
//		)->then(
//			self::mockCallable(1, $message),
//			self::mockCallable(0)
//		);
//	}
//
//	public function testCompleteResolvesOnFulfilledCallback() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$sut = $promiseContainer->getPromise();
//		$expectedValue = "expected value";
//		$sut->complete(
//			self::mockCallable(1, $expectedValue)
//		);
//
//		$promiseContainer->resolve($expectedValue);
//	}
//
//	public function testCompleteCallsOnFulfilledForPreResolvedPromise() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("example");
//		$sut = $promiseContainer->getPromise();
//
//		$onFulfilled = self::mockCallable(
//			1,
//			"example"
//		);
//		$sut->complete($onFulfilled);
//	}
//
//	public function testCompleteCallsOnRejectedForRejectedPromise() {
//		$exception = new Exception("Completed but rejected");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//		$sut = $promiseContainer->getPromise();
//		$sut->complete(
//			null,
//			self::mockCallable(1, $exception)
//		);
//	}
//
//	public function testCompleteThrowsExceptionWithNoHandler() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("example");
//		$sut = $promiseContainer->getPromise();
//
//		self::expectException(PromiseException::class);
//
//		$sut->complete(function() {
//			throw new PromiseException("This is not handled");
//		});
//	}
//
//	public function testCompleteThrowsExceptionWithNoRejectionHandler() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject(new Exception());
//		$sut = $promiseContainer->getPromise();
//
//		self::expectException(PromiseException::class);
//
//		$sut->complete(
//			null,
//			function() {
//				throw new PromiseException("This is not handled");
//			}
//		);
//	}
//
//	public function testCompleteThrowsExceptionWithoutOnFulfilledOnRejectedHandlers() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$rejectionMessage = "Example rejection message";
//		$promiseContainer->reject(new Exception($rejectionMessage));
//		$sut = $promiseContainer->getPromise();
//		self::expectExceptionMessage($rejectionMessage);
//		$sut->complete(/* pass no fulfil/reject handler */);
//	}
//
//	public function testCompleteShouldNotContinueThrowingWhenExceptionCaught() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject(new Exception());
//
//		$sut = $promiseContainer->getPromise();
//		$exception = null;
//
//		try {
//			$sut->complete(
//				null,
//				function(Throwable $reason) {
//// Do nothing, essentially "catching" the exception.
//				}
//			);
//		}
//		catch(Exception $exception) {
//// Catching any exception will mean $exception is not null.
//		}
//
//		self::assertNull($exception);
//	}
//
//	public function testCatchCalledForRejectedPromise() {
//		$exception = new Exception("Example");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//
//		$sut = $promiseContainer->getPromise();
//		$sut->catch(
//			self::mockCallable(1, $exception),
//		);
//	}
//
//	public function testCatchRejectionReasonIdenticalToRejectionException() {
//		$exception = new Exception("Example");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//
//		$onRejected = self::mockCallable(1, $exception);
//
//		$sut = $promiseContainer->getPromise();
//		$sut->catch(function($reason) use($onRejected) {
//			call_user_func($onRejected, $reason);
//		});
//	}
//
//	public function testCatchRejectionHandlerIsCalledByTypeHintedOnRejectedCallback() {
//		$exception = new PromiseException("Example");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//		$sut = $promiseContainer->getPromise();
//
//		$onRejected = self::mockCallable(1, $exception);
//
//		$sut->catch(function(PromiseException $reason) use($onRejected) {
//			call_user_func($onRejected, $reason);
//		});
//	}
//
//	public function testCatchRejectionHandlerIsNotCalledByTypeHintedOnRejectedCallback() {
//		$exception = new RangeException();
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//		$sut = $promiseContainer->getPromise();
//
//		$onRejected = self::mockCallable(0);
//
//		$sut->catch(function(PromiseException $reason) use($onRejected) {
//			call_user_Func($onRejected, $reason);
//		});
//	}
//
//	public function testCatchNotCalledOnFulfilledPromise() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("example");
//		$sut = $promiseContainer->getPromise();
//		$sut->catch(self::mockCallable(0));
//	}
//
//	public function testFinallyDoesNotBlockOnFulfilled() {
//		$expectedValue = "Don't break promises!";
//		$promiseContainer = $this->getTestPromiseContainer();
//
//		$sut = $promiseContainer->getPromise();
//		$sut->finally(fn() => "example123")
//			->then(self::mockCallable(1, $expectedValue));
//
//		$promiseContainer->resolve($expectedValue);
//	}
//
//	public function testFinallyDoesNotBlockOnRejected() {
//		$exception = new Exception();
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//		$sut = $promiseContainer->getPromise();
//		$sut->finally(function() {})
//			->then(
//				null,
//				self::mockCallable(1, $exception),
//			);
//	}
//
//	public function testFinallyDoesNotBlockOnRejectedWhenReturnsScalar() {
//		$exception = new Exception();
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception);
//		$sut = $promiseContainer->getPromise();
//		$sut->finally(function() {
//			return "Arbitrary scalar value";
//		})->then(
//			null,
//			self::mockCallable(1, $exception),
//		);
//	}
//
//	public function testFinallyPassesThrownException() {
//		$exception1 = new Exception("First");
//		$exception2 = new Exception("Second");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject($exception1);
//
//		$sut = $promiseContainer->getPromise();
//		$sut->finally(function() use($exception2) {
//			throw $exception2;
//		})->then(
//			self::mockCallable(0),
//			self::mockCallable(1, $exception2),
//		);
//	}
//
//	public function testOnRejectedCalledWhenFinallyThrows() {
//		$exception = new PromiseException("Oh dear, oh dear");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("Example resolution");
//
//		$sut = $promiseContainer->getPromise();
//		$sut->finally(function() use($exception) {
//			throw $exception;
//		})->then(
//			self::mockCallable(0),
//			self::mockCallable(1, $exception)
//		);
//	}
//
//	public function testGetStatePending() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$sut = $promiseContainer->getPromise();
//		self::assertEquals(
//			HttpPromiseInterface::PENDING,
//			$sut->getState()
//		);
//	}
//
//	public function testGetStateFulfilled() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("Example resolution");
//		$sut = $promiseContainer->getPromise();
//
//		self::assertEquals(
//			HttpPromiseInterface::FULFILLED,
//			$sut->getState()
//		);
//	}
//
//	public function testGetStateRejected() {
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->reject(new Exception("Example rejection"));
//		$sut = $promiseContainer->getPromise();
//
//		self::assertEquals(
//			HttpPromiseInterface::REJECTED,
//			$sut->getState()
//		);
//	}
//
//	/**
//	 * This test is almost identical to the next one. Inside a try-catch
//	 * block, it executes a then-catch chain. It asserts that the catch
//	 * callback is provided the expected exception, and that the exception
//	 * does not bubble out and into the catch block.
//	 */
//	public function testCatchMethodNotBubblesThrowables() {
//		$expectedException = new Exception("Test exception");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("test");
//		$sut = $promiseContainer->getPromise();
//		$onRejected = self::mockCallable(1, $expectedException);
//
//		$exception = null;
//		try {
//			$sut->then(function() use($expectedException) {
//				throw $expectedException;
//			})->catch($onRejected);
//		}
//		catch(Throwable $exception) {}
//
//		self::assertNull($exception);
//	}
//
//	/**
//	 * This test tests the opposite of the previous one: if there is no
//	 * catch function in the promise chain, an exception should bubble up
//	 * and be caught by the try-catch block.
//	 */
//	public function testNoCatchMethodBubblesThrowables() {
//		$expectedException = new Exception("Test exception");
//		$promiseContainer = $this->getTestPromiseContainer();
//		$promiseContainer->resolve("test");
//		$sut = $promiseContainer->getPromise();
//
//		$exception = null;
//		try {
//			$sut->then(function() use($expectedException) {
//				throw $expectedException;
//			});
//		}
//		catch(Throwable $exception) {
//			var_dump($exception);
//		}
//
//		self::assertSame($expectedException, $exception);
//	}

	protected function getTestPromiseContainer():TestPromiseContainer {
		$resolveCallback = null;
		$rejectCallback = null;

		$promise = new Promise(function($resolve, $reject)
		use(&$resolveCallback, &$rejectCallback) {
			$resolveCallback = $resolve;
			$rejectCallback = $reject;
		});

		return new TestPromiseContainer(
			$promise,
			$resolveCallback,
			$rejectCallback,
			$resolveCallback
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