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
}
