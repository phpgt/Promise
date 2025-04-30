<?php
namespace Gt\Promise\Test;

use Exception;
use Gt\Promise\Deferred;
use PHPUnit\Framework\TestCase;

class DeferredTest extends TestCase {
	public function testEmptyProcessList() {
		$sut = new Deferred();
		self::assertEmpty($sut->getProcessList());
	}

	public function testConstructWithProcess() {
		$process = function() {};
		$sut = new Deferred($process);
		$processList = $sut->getProcessList();
		self::assertCount(1, $processList);
		self::assertSame($process, $processList[0]);
	}

	public function testResolveCompletes() {
		$sut = new Deferred();
		self::assertTrue($sut->isActive());
		$sut->resolve(123);
		self::assertFalse($sut->isActive());
	}

	public function testRejectCompletes() {
		$sut = new Deferred();
		self::assertTrue($sut->isActive());
		$sut->resolve(new Exception("Example"));
		self::assertFalse($sut->isActive());
	}

	public function testCompleteCallbackOnlyFiresOnce() {
		$numCalls = 0;
		$completeCallback = function() use (&$numCalls) {
			$numCalls++;
		};
		$sut = new Deferred();
		$sut->onComplete($completeCallback);
		$sut->resolve(123);
		$sut->reject(new Exception("Example"));
		self::assertEquals(1, $numCalls);
	}

	public function testResolvePromise() {
		$numResolvedCalls = 0;
		$numRejectedCalls = 0;

		$sut = new Deferred();
		$promise = $sut->getPromise();
		$promise->then(function() use (&$numResolvedCalls) {
			$numResolvedCalls++;
		}, function() use (&$numRejectedCalls) {
			$numRejectedCalls++;
		});

		$sut->resolve(123);
		self::assertEquals(1, $numResolvedCalls);
		self::assertEquals(0, $numRejectedCalls);
	}

	public function testRejectPromise() {
		$numResolvedCalls = 0;
		$numRejectedCalls = 0;

		$sut = new Deferred();
		$promise = $sut->getPromise();
		$promise->then(function() use (&$numResolvedCalls) {
			$numResolvedCalls++;
		})
		->catch(function() use (&$numRejectedCalls) {
			$numRejectedCalls++;
		});

		$sut->reject(new Exception("Example"));
		self::assertEquals(0, $numResolvedCalls);
		self::assertEquals(1, $numRejectedCalls);
	}

	public function testMultipleResolution() {
		$deferred = new Deferred();
		$promise = $deferred->getPromise();

		$received = [];

		$deferred->resolve("hello");

		$promise->then(function(string $thing) use(&$received) {
// 0: Should resolve with "hello", from the above Deferred::resolve() call
			array_push($received, $thing);
			return "$thing-appended-from-1";
		})->then(function(string $thing) use(&$received) {
// 1: Should resolve with "hello-appended-from-1", due to the previous chained function returning the appended string.
			array_push($received, $thing);
// This function does not return a value...
		})->then(function(mixed $thing) use(&$received) {
// ... so this chained function should never be called.
// Notice the type hint for the function is mixed, in case there's an attempt at resolving with null.
			array_push($received, $thing);
		});

// 2: This function is at the start of a promise chain, which is already resolved, so it should be resolved with the original resolution of "hello".
		$promise->then(function(string $thing) use(&$received) {
			array_push($received, $thing);
		});

// 3: This function is also at the start of a new promise chain, so it should also be resolved with "hello".
		$promise->then(function(string $thing) use(&$received) {
			array_push($received, $thing);
// but it doesn't return anything...
		})->then(function(string $thing) use(&$received) {
// ... so no future promises in this chain should be resolved.
			array_push($received, $thing);
		})->then(function(string $thing) use(&$received) {
			array_push($received, $thing);
		});

// The Deferred is resolved with a new value, but the Promises/A+ specification
// states that a promise should only resolve once, and subsequent resolutions
// should be ignored.
		$deferred->resolve("world");

		$promise->then(function(string $thing) use(&$received) {
// 4: This promise starts a new chain, so it should resolve with the original resolved value, "hello".
			array_push($received, $thing);
// but it doesn't return anything...
		})->then(function(string $thing) use (&$received) {
// ... so no future promises in this chain should be resolved.
			array_push($received, $thing);
		});

// The count should match the commented behaviour above, to verify that chains
// after a non-returning handler are not invoked.
		self::assertCount(5, $received);
		self::assertSame("hello", $received[0], "String check 0");
		self::assertSame("hello-appended-from-1", $received[1], "String check 1");
		self::assertSame("hello", $received[2], "String check 2");
		self::assertSame("hello", $received[3], "String check 3");
		self::assertSame("hello", $received[4], "String check 4");
	}
}
