<?php
namespace Gt\Promise\Test;

use Closure;
use Exception;
use Gt\Promise\FulfilledPromise;
use Gt\Promise\FulfilledValueNotConcreteException;
use Gt\Promise\Promise;
use Http\Promise\Promise as HttpPromiseInterface;
use PHPUnit\Framework\TestCase;
use TypeError;

class FulfilledPromiseTest extends TestCase {
	public function testCanNotResolveWithPromise() {
		$callback = fn()=>true;
		$promise = new Promise($callback);
		self::expectException(FulfilledValueNotConcreteException::class);
		new FulfilledPromise($promise);
	}

	public function testDoNothingWithNullComplete() {
		$message = "Test message";
		$sut = new FulfilledPromise($message);
		$exception = null;
		try {
			$sut->complete();
		}
		catch(Exception $exception) {}

		self::assertNull($exception);
	}

	public function testCompleteWithPromise() {
		$message = "Test message";

		$promise = self::createMock(Promise::class);
		$promise->expects(self::once())
			->method("complete");
		$callback = fn() => $promise;

		$sut = new FulfilledPromise($message);
		$sut->complete($callback);
	}

	public function testCatch() {
		// Catch does nothing because a FulfilledPromise is already resolved.
		$callCount = 0;
		$callback = function() use(&$callCount) {
			$callCount++;
		};
		$sut = new FulfilledPromise(true);
		self::assertSame($sut, $sut->catch($callback));
		self::assertEquals(0, $callCount);
	}

	public function testFinally() {
		$callCount = 0;
		$callback = function() use(&$callCount) {
			$callCount++;
		};

		$message = "Test message";
		$sut = new FulfilledPromise($message);
		$sut->finally($callback);
		self::assertEquals(1, $callCount);
	}

	public function testCompleteWithInvalidCallback() {
		$callback = function(string $requiredStringParameter) {};

		$sut = new FulfilledPromise("Callback should not have a required string parameter!");
		$reasonArray = [];
		$sut->finally($callback)->catch(function(\Throwable $reason) use (&$reasonArray) {
			array_push($reasonArray, $reason);
		});
		self::assertCount(1, $reasonArray);
		self::assertInstanceOf(TypeError::class, $reasonArray[0]);
	}

	public function testGetState() {
		$sut = new FulfilledPromise();
		self::assertEquals(
			HttpPromiseInterface::FULFILLED,
			$sut->getState()
		);
	}
}