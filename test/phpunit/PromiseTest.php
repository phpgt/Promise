<?php
namespace Gt\Promise\Test;

use Exception;
use Gt\Promise\Promise;
use Gt\Promise\PromiseException;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RangeException;
use stdClass;
use Throwable;

class PromiseTest extends TestCase {
	public function testOnFulfilledResolvesCorrectValue() {
		$value = "Example resolution value";
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve($value);
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(1, $value)
		);
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
	}

	public function testImmutabilityOfResolvedPromise() {
		$value = "A resolved promise should by immutable";
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->then(function($value) use($promiseContainer) {
			$promiseContainer->resolve("Something else");
			return $value;
		})->then(
			self::mockCallable(1, $value),
			self::mockCallable(0),
		);

		$promiseContainer->resolve($value);
		$promiseContainer->resolve("This should resolve another promise");
	}

	public function testPromiseRejectsIfResolvedWithItself() {
		$actualMessage = null;

		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(0),
			function(PromiseException $reason) use(&$actualMessage) {
				$actualMessage = $reason->getMessage();
			}
		);

		$promiseContainer->resolve($sut);
		self::assertEquals(
			"A Promise must be settled with a concrete value, not another Promise.",
			$actualMessage
		);
	}

	public function testOnFulfillShouldRejectWhenResolvedWithPromiseInSameChain() {
		$actualMessage = null;

		$promiseContainer1 = $this->getTestPromiseContainer();
		$promiseContainer2 = $this->getTestPromiseContainer();
		$sut1 = $promiseContainer1->getPromise();
		$sut2 = $promiseContainer2->getPromise();

		$sut2->then(
			self::mockCallable(0),
			function(LogicException $reason) use(&$actualMessage) {
				$actualMessage = $reason->getMessage();
			}
		);

		$promiseContainer1->resolve($sut2);
		$promiseContainer2->resolve($sut1);
	}

	public function testRejectWithException() {
		$exception = new Exception("Example");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$sut->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception)
		);

		$promiseContainer->reject($exception);
	}

	public function testRejectForwardsException() {
		$exception = new Exception("Forward me!");
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();

		$sut->then(
			self::mockCallable(0)
		)->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception),
		);

		$promiseContainer->reject($exception);
	}

	public function testRejectIfResolverThrowsException() {
		$exception = new Exception("first-test");

		$resolver = function() use($exception) {
			throw $exception;
		};

		$sut = new Promise($resolver);

		$onFulfilled = self::mockCallable(0);
		$onRejected = self::mockCallable(
			1,
			$exception
		);

		$sut->then($onFulfilled, $onRejected);
	}

	public function testRejectIfRejecterThrowsException() {
		$exception = new Exception("another-test");

		$promiseContainer = self::getTestPromiseContainer();
		$promiseContainer->reject(new Exception());

		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(0),
			self::mockCallableThrowsException($exception, 1),
		)->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception),
		);
	}

	public function testImmutabilityOfOriginalPromise()
	{
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("example1");
		$promiseContainer->resolve("example2");
		$onFulfilled = self::mockCallable(1);
		$onRejected = self::mockCallable(0);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);
	}

	public function testImmutabilityOfOriginalRejectedPromise() {
		$promiseContainer = $this->getTestPromiseContainer();
		$exception1 = new Exception("First exception");
		$exception2 = new Exception("Second exception");

		$promiseContainer->reject($exception1);
		$promiseContainer->reject($exception2);

		$onFulfilled = self::mockCallable(0);
		$onRejected = self::mockCallable(1, $exception1);

		$sut = $promiseContainer->getPromise();
		$sut->then(
			$onFulfilled,
			$onRejected
		);
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
		);
	}

	public function testThenCallbackResultForwarded() {
		$message = "Hello";
		$messageConcat = "PHP.Gt!";
		$expectedResolvedMessage = "$message, $messageConcat";

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
		})->then(
			$onFulfilled,
			$onRejected
		);
	}

	public function testThenRejectionCallbackResultForwarded() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject(new Exception(
			"Reject only the first promise"
		));

		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(0),
			fn() => null,
		)->then(
			self::mockCallable(1),
			self::mockCallable(0),
		);
	}

	public function testThenCallsOnRejectedWhenExceptionThrown() {
		$promiseContainer = $this->getTestPromiseContainer();
		$exception = new Exception();

		$promiseContainer->resolve("example");
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallableThrowsException($exception, 1),
			self::mockCallable(0)
		)->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception)
		);
	}

	public function testThenProvidedResolvedValueAfterRejectionReturnsValue() {
		$message = "If a rejection returns a value, the next chained "
			. "promise should resolve with the value";

		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject(new Exception());
		$sut = $promiseContainer->getPromise();
		$sut->then(
			self::mockCallable(0),
			fn() => $message,
		)->then(
			self::mockCallable(1, $message),
			self::mockCallable(0)
		);
	}

	public function testCompleteResolvesOnFulfilledCallback() {
		$promiseContainer = $this->getTestPromiseContainer();
		$sut = $promiseContainer->getPromise();
		$expectedValue = "expected value";
		$sut->complete(
			self::mockCallable(1, $expectedValue)
		);

		$promiseContainer->resolve($expectedValue);
	}

	public function testCompleteCallsOnFulfilledForPreResolvedPromise() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("example");
		$sut = $promiseContainer->getPromise();

		$onFulfilled = self::mockCallable(
			1,
			"example"
		);
		$sut->complete($onFulfilled);
	}

	public function testCompleteCallsOnRejectedForRejectedPromise() {
		$exception = new Exception("Completed but rejected");
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception);
		$sut = $promiseContainer->getPromise();
		$sut->complete(
			null,
			self::mockCallable(1, $exception)
		);
	}

	public function testCompleteThrowsExceptionWithNoHandler() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("example");
		$sut = $promiseContainer->getPromise();

		self::expectException(PromiseException::class);

		$sut->complete(function() {
			throw new PromiseException("This is not handled");
		});
	}

	public function testCompleteThrowsExceptionWithNoRejectionHandler() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject(new Exception());
		$sut = $promiseContainer->getPromise();

		self::expectException(PromiseException::class);

		$sut->complete(
			null,
			function() {
				throw new PromiseException("This is not handled");
			}
		);
	}

	public function testCompleteThrowsExceptionWithoutOnFulfilledOnRejectedHandlers() {
		$promiseContainer = $this->getTestPromiseContainer();
		$rejectionMessage = "Example rejection message";
		$promiseContainer->reject(new Exception($rejectionMessage));
		$sut = $promiseContainer->getPromise();
		self::expectExceptionMessage($rejectionMessage);
		$sut->complete(/* pass no fulfil/reject handler */);
	}

	public function testCompleteShouldNotContinueThrowingWhenExceptionCaught() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject(new Exception());

		$sut = $promiseContainer->getPromise();
		$exception = null;

		try {
			$sut->complete(
				null,
				function(Throwable $reason) {
// Do nothing, essentially "catching" the exception.
				}
			);
		}
		catch(Exception $exception) {
// Catching any exception will mean $exception is not null.
		}

		self::assertNull($exception);
	}

	public function testCatchCalledForRejectedPromise() {
		$exception = new Exception("Example");
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception);

		$sut = $promiseContainer->getPromise();
		$sut->catch(
			self::mockCallable(1, $exception),
		);
	}

	public function testCatchRejectionReasonIdenticalToRejectionException() {
		$exception = new Exception("Example");
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception);

		$onRejected = self::mockCallable(1, $exception);

		$sut = $promiseContainer->getPromise();
		$sut->catch(function($reason) use($onRejected) {
			call_user_func($onRejected, $reason);
		});
	}

	public function testCatchRejectionHandlerIsCalledByTypeHintedOnRejectedCallback() {
		$exception = new PromiseException("Example");
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception);
		$sut = $promiseContainer->getPromise();

		$onRejected = self::mockCallable(1, $exception);

		$sut->catch(function(PromiseException $reason) use($onRejected) {
			call_user_func($onRejected, $reason);
		});
	}

	public function testCatchRejectionHandlerIsNotCalledByTypeHintedOnRejectedCallback() {
		$exception = new RangeException();
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception);
		$sut = $promiseContainer->getPromise();

		$onRejected = self::mockCallable(0);

		$sut->catch(function(PromiseException $reason) use($onRejected) {
			call_user_Func($onRejected, $reason);
		});
	}

	public function testCatchNotCalledOnFulfilledPromise() {
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("example");
		$sut = $promiseContainer->getPromise();
		$sut->catch(self::mockCallable(0));
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
		$promiseContainer->reject($exception);
		$sut = $promiseContainer->getPromise();
		$sut->finally(function() {})
			->then(
				null,
				self::mockCallable(1, $exception),
			);
	}

	public function testFinallyDoesNotBlockOnRejectedWhenReturnsScalar() {
		$exception = new Exception();
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception);
		$sut = $promiseContainer->getPromise();
		$sut->finally(function() {
			return "Arbitrary scalar value";
		})->then(
			null,
			self::mockCallable(1, $exception),
		);
	}

	public function testFinallyPassesThrownException() {
		$exception1 = new Exception("First");
		$exception2 = new Exception("Second");
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->reject($exception1);

		$sut = $promiseContainer->getPromise();
		$sut->finally(function() use($exception2) {
			throw $exception2;
		})->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception2),
		);
	}

	public function testOnRejectedCalledWhenFinallyThrows() {
		$exception = new PromiseException("Oh dear, oh dear");
		$promiseContainer = $this->getTestPromiseContainer();
		$promiseContainer->resolve("Example resolution");

		$sut = $promiseContainer->getPromise();
		$sut->finally(function() use($exception) {
			throw $exception;
		})->then(
			self::mockCallable(0),
			self::mockCallable(1, $exception)
		);
	}

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